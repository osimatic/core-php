<?php

namespace Osimatic\Security;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * File-based rate limiter used to protect costly public endpoints (e.g. third-party API calls, payment session creation) from simple abuse originating from a single client.
 * Counters are persisted as one JSON file per key on local disk, guarded by an exclusive lock, so this implementation is only accurate on a single server; deployments with several application servers behind a load balancer need a centralized store (e.g. Redis, APCu) instead.
 *
 * @link https://en.wikipedia.org/wiki/Rate_limiting Rate limiting
 */
class RateLimiter
{
	// ========================================
	// Constants
	// ========================================

	/** Additional delay (in seconds), beyond the end of its own time window, before a counter file is considered stale and eligible for cleanup */
	private const int STALE_FILE_GRACE_PERIOD_SECONDS = 3600;

	/** Default value for $cleanupProbability: cleanup of stale counter files runs on average once every N calls to check(), to keep the hot path cheap */
	public const int DEFAULT_CLEANUP_PROBABILITY = 100;

	// ========================================
	// Constructor & Configuration
	// ========================================

	/**
	 * @param string $storageDirectory Directory where counter files are stored (default: system temp directory)
	 * @param int $cleanupProbability Cleanup of stale counter files runs on average once every $cleanupProbability calls to check(); use 1 to always clean up (e.g. in tests)
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 */
	public function __construct(
		private string $storageDirectory = '',
		private int $cleanupProbability = self::DEFAULT_CLEANUP_PROBABILITY,
		private readonly LoggerInterface $logger = new NullLogger(),
	)
	{
		if ('' === $this->storageDirectory) {
			$this->storageDirectory = sys_get_temp_dir();
		}
		if ($this->cleanupProbability < 1) {
			$this->cleanupProbability = 1;
		}
	}

	/**
	 * Sets the directory where counter files are stored.
	 *
	 * @param string $storageDirectory Directory where counter files are stored
	 * @return self Returns this instance for method chaining
	 */
	public function setStorageDirectory(string $storageDirectory): self
	{
		$this->storageDirectory = $storageDirectory;
		return $this;
	}

	/**
	 * Sets how often the stale-file cleanup pass runs, on average once every $cleanupProbability calls to check().
	 *
	 * @param int $cleanupProbability Use 1 to always clean up (e.g. in tests)
	 * @return self Returns this instance for method chaining
	 */
	public function setCleanupProbability(int $cleanupProbability): self
	{
		$this->cleanupProbability = max(1, $cleanupProbability);
		return $this;
	}

	// ========================================
	// Public Methods
	// ========================================

	/**
	 * Checks whether a request identified by $key is within the allowed rate, and increments its counter.
	 * The counter resets automatically once $windowSeconds have elapsed since it was first incremented.
	 *
	 * @param string $key Unique identifier for the rate-limited scope (e.g. 'smoobu_' . $clientIp)
	 * @param int $maxRequests Maximum number of requests allowed within the time window
	 * @param int $windowSeconds Duration of the time window, in seconds
	 * @return bool True if the request is allowed, false if the limit has been exceeded
	 */
	public function check(string $key, int $maxRequests, int $windowSeconds): bool
	{
		$this->cleanupStaleFilesRandomly();

		$file = $this->getFilePath($key);

		$fp = @fopen($file, 'c+');
		if (false === $fp) {
			$this->logger->error('Rate limiter storage file could not be opened; failing open (request allowed without being counted).', ['file' => $file]);
			return true;
		}

		flock($fp, LOCK_EX);

		$data = json_decode(stream_get_contents($fp) ?: '', true);
		$now = time();

		if (!is_array($data) || $now > ($data['reset'] ?? 0)) {
			$data = ['count' => 0, 'reset' => $now + $windowSeconds];
		}

		$data['count']++;
		$allowed = $data['count'] <= $maxRequests;

		ftruncate($fp, 0);
		rewind($fp);
		fwrite($fp, json_encode($data));
		fflush($fp);
		flock($fp, LOCK_UN);
		fclose($fp);

		if (!$allowed) {
			$this->logger->info('Rate limit exceeded.', ['key' => $key, 'max_requests' => $maxRequests, 'window_seconds' => $windowSeconds]);
		}

		return $allowed;
	}

	// ========================================
	// Helper Methods
	// ========================================

	/**
	 * Builds the path of the counter file associated with a given key.
	 *
	 * @param string $key Unique identifier for the rate-limited scope
	 * @return string Absolute path of the counter file
	 */
	private function getFilePath(string $key): string
	{
		return rtrim($this->storageDirectory, '/\\') . DIRECTORY_SEPARATOR . 'ratelimit_' . md5($key) . '.json';
	}

	/**
	 * Randomly triggers a cleanup pass that deletes counter files whose time window expired a while ago,
	 * to avoid unbounded growth of the storage directory (one file is created per distinct key ever seen).
	 */
	private function cleanupStaleFilesRandomly(): void
	{
		if (1 !== random_int(1, $this->cleanupProbability)) {
			return;
		}

		$now = time();
		foreach (glob(rtrim($this->storageDirectory, '/\\') . DIRECTORY_SEPARATOR . 'ratelimit_*.json') ?: [] as $file) {
			$data = json_decode(@file_get_contents($file) ?: '', true);
			$reset = is_array($data) ? ($data['reset'] ?? 0) : 0;

			if ($now > $reset + self::STALE_FILE_GRACE_PERIOD_SECONDS) {
				@unlink($file);
			}
		}
	}
}