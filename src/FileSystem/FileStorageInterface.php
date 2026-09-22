<?php

namespace Osimatic\FileSystem;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Interface for file storage implementations (local filesystem, cloud object storage, etc.).
 * This interface must be implemented by any class that provides file write, existence check, deletion and URL resolution for a given storage key.
 */
interface FileStorageInterface
{
	/**
	 * Writes a local file to the storage under the given key.
	 * @param string $key The storage key (relative path) under which the file is stored
	 * @param string $localFilePath The path of the local file to write
	 * @param bool $public Whether the file should be publicly readable via getUrl() (default true); pass false for sensitive files that must only be accessed via getTemporaryUrl()
	 * @return bool True on success, false on failure
	 */
	public function write(string $key, string $localFilePath, bool $public = true): bool;

	/**
	 * Uploads a file (from an HTTP request) to the storage under the given key.
	 * Handles both traditional file uploads and InputFile objects (base64 data).
	 * @param string $key The storage key (relative path) under which the file is stored
	 * @param InputFile|UploadedFile $uploadedFile The uploaded file to store
	 * @param bool $public Whether the file should be publicly readable via getUrl() (default true); pass false for sensitive files that must only be accessed via getTemporaryUrl()
	 * @return bool True on success, false on failure
	 */
	public function upload(string $key, InputFile|UploadedFile $uploadedFile, bool $public = true): bool;

	/**
	 * Reads and returns the content of the file stored under the given key.
	 * @param string $key The storage key of the file to read
	 * @return string|null The file content, or null if the file does not exist or could not be read
	 */
	public function read(string $key): ?string;

	/**
	 * Returns metadata (size in bytes, MIME type) for the file stored under the given key.
	 * @param string $key The storage key of the file
	 * @return array{size: int, mimeType: string}|null null if the file does not exist or could not be read
	 */
	public function metadata(string $key): ?array;

	/**
	 * Checks whether a file exists in the storage for the given key.
	 * @param string $key The storage key to check
	 * @return bool True if the file exists, false otherwise
	 */
	public function exists(string $key): bool;

	/**
	 * Deletes the file stored under the given key.
	 * @param string $key The storage key of the file to delete
	 * @return bool True on success, false on failure
	 */
	public function delete(string $key): bool;

	/**
	 * Copies a file within the storage from one key to another, keeping the source file in place.
	 * @param string $sourceKey The storage key of the file to copy
	 * @param string $destinationKey The storage key to copy the file to
	 * @return bool True on success, false on failure
	 */
	public function copy(string $sourceKey, string $destinationKey): bool;

	/**
	 * Renames (moves) a file within the storage from one key to another.
	 * @param string $sourceKey The current storage key of the file
	 * @param string $destinationKey The new storage key of the file
	 * @return bool True on success, false on failure
	 */
	public function rename(string $sourceKey, string $destinationKey): bool;

	/**
	 * Returns the publicly accessible URL for the file stored under the given key.
	 * This method never performs a network call and does not check that the file actually exists.
	 * @param string $key The storage key of the file
	 * @return string The public URL of the file
	 */
	public function getUrl(string $key): string;

	/**
	 * Returns a temporary, expiring URL for the file stored under the given key, suitable for files written with public: false.
	 * @param string $key The storage key of the file
	 * @param \DateTimeImmutable $expiration The date and time at which the returned URL stops being valid
	 * @return string The temporary URL of the file
	 */
	public function getTemporaryUrl(string $key, \DateTimeImmutable $expiration): string;
}