<?php

namespace Osimatic\Security;

use Osimatic\Cache\StoreInterface;
use Osimatic\Network\HTTPHeaders;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Symfony service that verifies HMAC-SHA256 signatures on incoming HTTP requests
 * to prevent tampering and replay attacks.
 *
 * The signing scheme uses a canonical string composed of:
 *   - A Unix timestamp (X-Timestamp header)
 *   - A one-time nonce (X-Nonce header)
 *   - Alphabetically sorted key=value pairs for each signed field
 *
 * The list of signed fields is defined by the caller, allowing this service to be used
 * for any type of request regardless of the domain.
 *
 * @link https://en.wikipedia.org/wiki/HMAC HMAC
 * @link https://en.wikipedia.org/wiki/Replay_attack Replay attack
 * @link https://en.wikipedia.org/wiki/Cryptographic_nonce Cryptographic nonce
 */
class RequestSignatureVerifier
{
	// ========================================
	// Constants
	// ========================================

	/**
	 * Maximum allowed difference in seconds between the request timestamp and the server time.
	 * Used as the default tolerance when none is provided.
	 */
	public const int DEFAULT_TIMESTAMP_TOLERANCE_SECONDS = 300; // ±5 minutes

	/**
	 * Prefix of the keys under which used nonces are recorded in the store.
	 */
	public const string NONCE_KEY_PREFIX = 'hmac_nonce:';

	// ========================================
	// Constructor & Configuration
	// ========================================

	/**
	 * @param string $secret Shared secret known to both the server and the client
	 * @param int $toleranceSeconds Maximum age of a valid request in seconds (default: 300)
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 * @param StoreInterface|null $store Store recording used nonces to reject replayed requests (null: no replay protection)
	 */
	public function __construct(
		private string $secret = '',
		private int $toleranceSeconds = self::DEFAULT_TIMESTAMP_TOLERANCE_SECONDS,
		private readonly LoggerInterface $logger = new NullLogger(),
		private readonly ?StoreInterface $store = null,
	)
	{}

	/**
	 * Sets the shared secret used to verify request signatures.
	 *
	 * @param string $secret Shared secret known to both the server and the client
	 * @return self
	 */
	public function setSecret(string $secret): self
	{
		$this->secret = $secret;

		return $this;
	}

	/**
	 * Sets the maximum age of a valid request in seconds.
	 *
	 * @param int $toleranceSeconds Tolerance window in seconds
	 * @return self
	 */
	public function setToleranceSeconds(int $toleranceSeconds): self
	{
		$this->toleranceSeconds = $toleranceSeconds;

		return $this;
	}

	// ========================================
	// Verification Methods
	// ========================================

	/**
	 * Verifies the HMAC-SHA256 signature of an incoming request.
	 *
	 * Performs three checks in order:
	 *   1. Timestamp freshness — rejects requests older than the configured tolerance
	 *   2. Signature integrity — rejects tampered payloads (checked before the nonce, so a forged request cannot consume a nonce)
	 *   3. Nonce uniqueness — rejects replayed requests, when a store is configured
	 *
	 * The caller is responsible for providing the exact list of fields to sign,
	 * sorted alphabetically, matching the signing logic on the client side.
	 *
	 * @param array $postData Request POST body
	 * @param array $headers HTTP headers containing X-Timestamp, X-Nonce, and X-Signature
	 * @param array $signedFields Alphabetically sorted list of field names to include in the signature
	 * @return bool True if the signature is valid and the request is fresh, false otherwise
	 */
	public function verify(array $postData, array $headers, array $signedFields): bool
	{
		// Les en-têtes HTTP sont insensibles à la casse (Symfony les normalise en minuscules)
		$headers = HTTPHeaders::normalize($headers);
		$timestamp = $headers['x-timestamp'] ?? null;
		$nonce = $headers['x-nonce'] ?? null;
		$signature = $headers['x-signature'] ?? null;

		if (!$timestamp || !$nonce || !$signature) {
			$this->logger->warning('Request signature verification failed: missing headers.', [
				'has_timestamp' => isset($headers['x-timestamp']),
				'has_nonce' => isset($headers['x-nonce']),
				'has_signature' => isset($headers['x-signature']),
			]);
			return false;
		}

		// 1. Reject requests with an expired timestamp.
		if (abs(time() - (int)$timestamp) > $this->toleranceSeconds) {
			$this->logger->warning('Request signature verification failed: timestamp expired.', [
				'timestamp' => $timestamp,
				'server_time' => time(),
				'tolerance_seconds' => $this->toleranceSeconds,
			]);
			return false;
		}

		// 2. Rebuild the canonical string and compare signatures.
		$expected = $this->generateSignature($timestamp, $nonce, $signedFields, $postData);

		if (!hash_equals($expected, $signature)) {
			$this->logger->warning('Request signature verification failed: signature mismatch.', [
				'nonce' => $nonce,
				'signed_fields' => $signedFields,
			]);
			return false;
		}

		// 3. Reject replayed requests: the nonce is recorded atomically, only once the signature is valid.
		if (null !== $this->store && !$this->store->addIfAbsent(self::NONCE_KEY_PREFIX . $nonce, $this->toleranceSeconds)) {
			$this->logger->warning('Request signature verification failed: nonce already used.', [
				'nonce' => $nonce,
			]);
			return false;
		}

		$this->logger->debug('Request signature verified successfully.', [
			'nonce' => $nonce,
			'signed_fields' => $signedFields,
		]);

		return true;
	}

	// ========================================
	// Signature Generation Methods
	// ========================================

	/**
	 * Generates the HMAC-SHA256 signature for a request, using the same canonical
	 * string format expected by verify().
	 *
	 * Intended to be used by the client responsible for signing outgoing requests,
	 * and internally by verify() to compute the expected signature.
	 *
	 * @param string $timestamp Unix timestamp string to include in the signed payload
	 * @param string $nonce One-time random value to include in the signed payload
	 * @param array $signedFields Ordered list of field names to include in the signature
	 * @param array $postData Request POST body used to resolve field values
	 * @return string The base64-encoded HMAC-SHA256 signature
	 */
	public function generateSignature(string $timestamp, string $nonce, array $signedFields, array $postData): string
	{
		$canonical = $this->buildCanonical($timestamp, $nonce, $signedFields, $postData);

		return base64_encode(hash_hmac('sha256', $canonical, $this->secret, true));
	}

	// ========================================
	// Helper Methods
	// ========================================

	/**
	 * Builds the canonical string that both the server and the client must sign independently.
	 *
	 * Format:
	 * <pre>
	 *   {timestamp}\n
	 *   {nonce}\n
	 *   field1=value1&field2=value2   (keys in the order provided, no trailing '&')
	 * </pre>
	 *
	 * Missing fields are included with an empty string value to ensure
	 * a deterministic canonical form on both sides.
	 *
	 * @param string $timestamp Unix timestamp string from the X-Timestamp header
	 * @param string $nonce One-time random value from the X-Nonce header
	 * @param array $signedFields Ordered list of field names to include
	 * @param array $postData Request POST body used to resolve field values
	 * @return string The canonical string ready to be signed
	 */
	private function buildCanonical(
		string $timestamp,
		string $nonce,
		array $signedFields,
		array $postData
	): string
	{
		$parts = [];
		foreach ($signedFields as $key) {
			$parts[] = $key . '=' . (isset($postData[$key]) ? (string)$postData[$key] : '');
		}

		return $timestamp . "\n" . $nonce . "\n" . implode('&', $parts);
	}
}