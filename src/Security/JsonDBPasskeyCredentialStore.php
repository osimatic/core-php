<?php

namespace Osimatic\Security;

use Osimatic\Data\JsonDB;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Passkey credential store backed by a JSON file, through JsonDB.
 * Binary values (credential ID, user ID) are stored base64-encoded and dates in ISO 8601 format. Only public data is stored, but the file should still be kept out of any web-accessible location.
 *
 * @link https://www.php.net/manual/en/function.base64-encode.php base64_encode
 */
class JsonDBPasskeyCredentialStore implements PasskeyCredentialStoreInterface
{
	// ========================================
	// Constants
	// ========================================

	/** Default name of the JSON file holding the credentials */
	public const string DEFAULT_FILENAME = 'passkeys.json';

	// ========================================
	// Constructor & Configuration
	// ========================================

	/**
	 * @param JsonDB $jsonDB The JsonDB instance giving access to the data directory
	 * @param string $filename Name of the JSON file holding the credentials
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 */
	public function __construct(
		private readonly JsonDB $jsonDB,
		private readonly string $filename = self::DEFAULT_FILENAME,
		private readonly LoggerInterface $logger = new NullLogger(),
	) {}

	// ========================================
	// Public Methods
	// ========================================

	/**
	 * {@inheritDoc}
	 */
	public function findAll(): array
	{
		$credentials = [];
		foreach ($this->readRecords() as $record) {
			$credential = $this->createCredentialFromRecord($record);
			if (null !== $credential) {
				$credentials[] = $credential;
			}
		}
		return $credentials;
	}

	/**
	 * {@inheritDoc}
	 */
	public function findByUserId(string $userId): array
	{
		return array_values(array_filter($this->findAll(), static fn(PasskeyCredential $credential): bool => $credential->getUserId() === $userId));
	}

	/**
	 * {@inheritDoc}
	 */
	public function findByCredentialId(string $credentialId): ?PasskeyCredential
	{
		foreach ($this->findAll() as $credential) {
			// The credential ID is public, so a plain comparison is enough
			if ($credential->getCredentialId() === $credentialId) {
				return $credential;
			}
		}
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function save(PasskeyCredential $credential): bool
	{
		$newRecord = $this->createRecordFromCredential($credential);

		return $this->updateRecords(static function (array $records) use ($newRecord): array {
			foreach ($records as $index => $record) {
				if (($record['credentialId'] ?? null) === $newRecord['credentialId']) {
					$records[$index] = $newRecord;
					return $records;
				}
			}
			$records[] = $newRecord;
			return $records;
		});
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete(string $credentialId): bool
	{
		$encodedCredentialId = base64_encode($credentialId);

		return $this->updateRecords(static function (array $records) use ($encodedCredentialId): ?array {
			$remainingRecords = array_values(array_filter($records, static fn(array $record): bool => ($record['credentialId'] ?? null) !== $encodedCredentialId));
			// Nothing to delete: no write needed
			return count($remainingRecords) === count($records) ? null : $remainingRecords;
		});
	}

	// ========================================
	// Helper Methods
	// ========================================

	/**
	 * Reads the raw records from the JSON file, under a shared lock so that a concurrent write is never seen half done.
	 *
	 * @return array[] The raw records (empty array if the file does not exist or cannot be read)
	 */
	private function readRecords(): array
	{
		try {
			if (!$this->jsonDB->exists($this->filename)) {
				return [];
			}
			return $this->withLock(LOCK_SH, fn(): array => $this->loadRecords() ?? []);
		} catch (\Throwable $e) {
			$this->logger->error('Unable to read the passkey credentials: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * Loads the raw records from the JSON file, without locking.
	 *
	 * @return array[]|null The raw records (empty array if the file does not exist), or null if the file cannot be read
	 */
	private function loadRecords(): ?array
	{
		try {
			return array_values(array_filter($this->jsonDB->read($this->filename), 'is_array'));
		} catch (\Throwable $e) {
			$this->logger->error('Unable to read the passkey credentials: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Applies a modification to the records, as an atomic read-modify-write protected by an exclusive lock.
	 * Nothing is written if the file cannot be read, so that an unreadable file is never overwritten with partial data.
	 *
	 * @param callable(array[]): (array[]|null) $update Receives the current raw records and returns the new ones, or null if there is nothing to write
	 * @return bool True if the records have been written, false otherwise
	 */
	private function updateRecords(callable $update): bool
	{
		try {
			return $this->withLock(LOCK_EX, function () use ($update): bool {
				$records = $this->loadRecords();
				if (null === $records) {
					return false;
				}

				$updatedRecords = $update($records);
				if (null === $updatedRecords) {
					return false;
				}

				$this->jsonDB->write($this->filename, array_values($updatedRecords));
				return true;
			});
		} catch (\Throwable $e) {
			$this->logger->error('Unable to write the passkey credentials: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Runs a callback while holding a lock on a companion lock file (the JSON file itself is rewritten on each save, so it cannot carry the lock).
	 *
	 * @param int $operation LOCK_SH for reading, LOCK_EX for writing
	 * @param callable(): mixed $callback The code to run under the lock
	 * @return mixed The value returned by the callback
	 * @throws \RuntimeException If the lock cannot be obtained
	 * @link https://www.php.net/manual/en/function.flock.php flock
	 */
	private function withLock(int $operation, callable $callback): mixed
	{
		$lockPath = $this->jsonDB->getFilePath($this->filename) . '.lock';

		$directory = dirname($lockPath);
		if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
			throw new \RuntimeException('Unable to create the data directory.');
		}

		$handle = @fopen($lockPath, 'c');
		if (false === $handle) {
			throw new \RuntimeException('Unable to open the lock file.');
		}

		try {
			if (!flock($handle, $operation)) {
				throw new \RuntimeException('Unable to obtain the lock.');
			}
			return $callback();
		} finally {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	/**
	 * Builds a raw record from a credential. Binary values are base64-encoded.
	 *
	 * @param PasskeyCredential $credential The credential
	 * @return array The raw record
	 */
	private function createRecordFromCredential(PasskeyCredential $credential): array
	{
		return [
			'credentialId' => base64_encode($credential->getCredentialId()),
			'publicKeyPem' => $credential->getPublicKeyPem(),
			'userId' => base64_encode($credential->getUserId()),
			'signatureCounter' => $credential->getSignatureCounter(),
			'name' => $credential->getName(),
			'createdAt' => $credential->getCreatedAt()->format(\DateTimeInterface::ATOM),
			'lastUsedAt' => $credential->getLastUsedAt()?->format(\DateTimeInterface::ATOM),
		];
	}

	/**
	 * Builds a credential from a raw record.
	 *
	 * @param array $record The raw record
	 * @return PasskeyCredential|null The credential, or null if the record is invalid
	 */
	private function createCredentialFromRecord(array $record): ?PasskeyCredential
	{
		$credentialId = base64_decode((string)($record['credentialId'] ?? ''), true);
		$userId = base64_decode((string)($record['userId'] ?? ''), true);
		if (false === $credentialId || false === $userId) {
			$this->logger->error('Invalid credential ID or user ID in the passkey credentials file.');
			return null;
		}

		try {
			return new PasskeyCredential(
				$credentialId,
				(string)($record['publicKeyPem'] ?? ''),
				$userId,
				(int)($record['signatureCounter'] ?? 0),
				(string)($record['name'] ?? ''),
				empty($record['createdAt']) ? null : new \DateTimeImmutable((string)$record['createdAt']),
				empty($record['lastUsedAt']) ? null : new \DateTimeImmutable((string)$record['lastUsedAt']),
			);
		} catch (\Exception $e) {
			// InvalidArgumentException (invalid value) or an invalid date
			$this->logger->error('Invalid record in the passkey credentials file: ' . $e->getMessage());
			return null;
		}
	}
}