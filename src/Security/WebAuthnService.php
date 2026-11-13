<?php

namespace Osimatic\Security;

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use Osimatic\Data\Encoding;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Server-side logic of passwordless authentication with passkeys (WebAuthn), built on top of lbuchs/webauthn.
 *
 * The flow works in two steps, for both registration and authentication:
 * 1. the server generates options containing a random challenge, sends them to the browser (JSON) and keeps the challenge (e.g. in the session);
 * 2. the browser asks the authenticator to sign the challenge and sends the result back, which the server verifies against the kept challenge.
 *
 * The challenge is single-use: callers must store it between the two steps and discard it after verification, whatever the outcome.
 * Every value coming from or going to the browser (credential IDs, clientDataJSON, attestationObject, authenticatorData, signature) is a base64url string, exactly as the browser JSON carries it: callers never have to encode or decode anything, the conversion to binary is done here, right before calling the library.
 * The only raw (binary) value is the challenge, which never leaves the server.
 * User verification (PIN or biometrics) is always required.
 *
 * @link https://www.w3.org/TR/webauthn-3/ Web Authentication specification
 * @link https://github.com/lbuchs/WebAuthn lbuchs/webauthn
 */
class WebAuthnService
{
	// ========================================
	// Constants
	// ========================================

	/** Timeout (in seconds) given to the browser to complete a registration or an authentication */
	public const int CEREMONY_TIMEOUT_SECONDS = 60;

	// ========================================
	// Constructor & Configuration
	// ========================================

	/**
	 * @param string $relyingPartyName Human-readable name of the site, displayed by the authenticator
	 * @param string $relyingPartyId Domain name of the site, without scheme or port (e.g. "www.example.com"), to which credentials are bound
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 * @param \Closure|null $webAuthnFactory Optional factory building the lbuchs/webauthn instance, called with the relying party name and ID and returning a WebAuthn (mainly useful to inject a mock in tests)
	 * @throws \RuntimeException If the OpenSSL extension is not available
	 */
	public function __construct(
		private readonly string $relyingPartyName,
		private readonly string $relyingPartyId,
		private readonly LoggerInterface $logger = new NullLogger(),
		private readonly ?\Closure $webAuthnFactory = null,
	)
	{
		// Fail early if WebAuthn cannot be used in this environment
		$this->createWebAuthn();
	}

	// ========================================
	// Registration Methods
	// ========================================

	/**
	 * Generates the options for registering a new passkey. The "options" part is to be sent to the browser as JSON (it is the argument of navigator.credentials.create()).
	 *
	 * @param string $userId Stable identifier of the user (binary string, 64 bytes max, must not contain personal data)
	 * @param string $userName Account name of the user, displayed by the authenticator
	 * @param string $userDisplayName Display name of the user, displayed by the authenticator
	 * @param string[] $excludeCredentialIds Base64url credential IDs (see PasskeyCredential::getCredentialId()) already registered, to prevent registering the same authenticator twice
	 * @return PasskeyCeremonyOptions The options to send to the browser, and the raw challenge to keep until verification
	 * @throws \InvalidArgumentException If an excluded credential ID is not a valid base64url string
	 * @link https://developer.mozilla.org/en-US/docs/Web/API/CredentialsContainer/create navigator.credentials.create()
	 */
	public function createRegistrationOptions(string $userId, string $userName, string $userDisplayName, array $excludeCredentialIds = []): PasskeyCeremonyOptions
	{
		$rawExcludeCredentialIds = array_map($this->decodeBase64Url(...), array_values($excludeCredentialIds));
		$webAuthn = $this->createWebAuthn();
		$options = $webAuthn->getCreateArgs($userId, $userName, $userDisplayName, self::CEREMONY_TIMEOUT_SECONDS, false, true, null, $rawExcludeCredentialIds);
		return new PasskeyCeremonyOptions($options, $webAuthn->getChallenge()->getBinaryString());
	}

	/**
	 * Verifies the response of the browser to a registration, and builds the credential to store.
	 *
	 * @param string $clientDataJSON Base64url clientDataJSON, as sent by the browser
	 * @param string $attestationObject Base64url attestationObject, as sent by the browser
	 * @param string $expectedChallenge The raw challenge returned by createRegistrationOptions()
	 * @param string $userId Identifier of the user registering the passkey (the one given to createRegistrationOptions())
	 * @param string $name Label of the passkey, to let the user tell his passkeys apart (empty if none)
	 * @return PasskeyCredential|null The credential to store if the registration is valid, null otherwise (including when a value is not valid base64url)
	 */
	public function verifyRegistration(string $clientDataJSON, string $attestationObject, string $expectedChallenge, string $userId, string $name = ''): ?PasskeyCredential
	{
		try {
			$data = $this->createWebAuthn()->processCreate($this->decodeBase64Url($clientDataJSON), $this->decodeBase64Url($attestationObject), $expectedChallenge, true);
			// The library gives the credential ID as binary: it is kept as base64url, the format used by the browser
			return new PasskeyCredential(Encoding::encodeBase64URL($data->credentialId), $data->credentialPublicKey, $userId, (int)($data->signatureCounter ?? 0), $name);
		} catch (WebAuthnException|\InvalidArgumentException|\TypeError $e) {
			// TypeError is caught on purpose: the library does not validate the types of the untrusted data sent by the browser (e.g. a malformed clientDataJSON)
			$this->logger->warning('Passkey registration rejected: ' . $e->getMessage());
			return null;
		}
	}

	// ========================================
	// Authentication Methods
	// ========================================

	/**
	 * Generates the options for authenticating with a registered passkey. The "options" part is to be sent to the browser as JSON (it is the argument of navigator.credentials.get()).
	 *
	 * @param string[] $credentialIds Base64url credential IDs (see PasskeyCredential::getCredentialId()) accepted for this authentication
	 * @return PasskeyCeremonyOptions The options to send to the browser, and the raw challenge to keep until verification
	 * @throws \InvalidArgumentException If a credential ID is not a valid base64url string
	 * @link https://developer.mozilla.org/en-US/docs/Web/API/CredentialsContainer/get navigator.credentials.get()
	 */
	public function createAuthenticationOptions(array $credentialIds): PasskeyCeremonyOptions
	{
		$rawCredentialIds = array_map($this->decodeBase64Url(...), array_values($credentialIds));
		$webAuthn = $this->createWebAuthn();
		$options = $webAuthn->getGetArgs($rawCredentialIds, self::CEREMONY_TIMEOUT_SECONDS, true, true, true, true, true, true);
		return new PasskeyCeremonyOptions($options, $webAuthn->getChallenge()->getBinaryString());
	}

	/**
	 * Verifies the response of the browser to an authentication: challenge, origin, signature and signature counter.
	 *
	 * @param PasskeyCredential $credential The registered credential matching the credential ID sent by the browser
	 * @param string $clientDataJSON Base64url clientDataJSON, as sent by the browser
	 * @param string $authenticatorData Base64url authenticatorData, as sent by the browser
	 * @param string $signature Base64url signature, as sent by the browser
	 * @param string $expectedChallenge The raw challenge returned by createAuthenticationOptions()
	 * @return PasskeyCredential|null The credential with its updated signature counter and last use date (to store again) if the authentication is valid, null otherwise (including when a value is not valid base64url)
	 */
	public function verifyAuthentication(PasskeyCredential $credential, string $clientDataJSON, string $authenticatorData, string $signature, string $expectedChallenge): ?PasskeyCredential
	{
		try {
			$webAuthn = $this->createWebAuthn();
			$webAuthn->processGet($this->decodeBase64Url($clientDataJSON), $this->decodeBase64Url($authenticatorData), $this->decodeBase64Url($signature), $credential->getPublicKeyPem(), $expectedChallenge, $credential->getSignatureCounter(), true);
			return $credential
				->withSignatureCounter($webAuthn->getSignatureCounter() ?? $credential->getSignatureCounter())
				->withLastUsedAt(new \DateTimeImmutable());
		} catch (WebAuthnException|\InvalidArgumentException|\TypeError $e) {
			$this->logger->warning('Passkey authentication rejected: ' . $e->getMessage());
			return null;
		}
	}

	// ========================================
	// Helper Methods
	// ========================================

	/**
	 * Decodes a base64url value received from the browser (or from the store) into binary, as expected by the library.
	 * An empty value is refused, since none of the values handled here can be empty.
	 *
	 * @param string $value The base64url string
	 * @return string The decoded binary string
	 * @throws \InvalidArgumentException If the value is empty or is not valid base64url
	 */
	private function decodeBase64Url(string $value): string
	{
		$decoded = Encoding::decodeBase64URL($value, true);
		if (false === $decoded || '' === $decoded) {
			throw new \InvalidArgumentException('Invalid base64url value.');
		}
		return $decoded;
	}

	/**
	 * Creates a fresh lbuchs/webauthn instance.
	 * A new instance is needed for every ceremony because the library keeps the challenge it generated inside the instance and would reuse it on a second call, whereas a challenge must be unique.
	 *
	 * @return WebAuthn A new instance
	 * @throws \RuntimeException If WebAuthn cannot be initialized (e.g. OpenSSL extension missing)
	 */
	private function createWebAuthn(): WebAuthn
	{
		if (null !== $this->webAuthnFactory) {
			return ($this->webAuthnFactory)($this->relyingPartyName, $this->relyingPartyId);
		}

		try {
			// The last argument makes binary values be encoded as base64url in the JSON sent to the browser
			return new WebAuthn($this->relyingPartyName, $this->relyingPartyId, null, true);
		} catch (WebAuthnException $e) {
			throw new \RuntimeException('Unable to initialize WebAuthn: ' . $e->getMessage(), 0, $e);
		}
	}
}