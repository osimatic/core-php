<?php

namespace Osimatic\FileSystem;

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
	 * @return bool True on success, false on failure
	 */
	public function write(string $key, string $localFilePath): bool;

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
	 * Returns the publicly accessible URL for the file stored under the given key.
	 * This method never performs a network call and does not check that the file actually exists.
	 * @param string $key The storage key of the file
	 * @return string The public URL of the file
	 */
	public function getUrl(string $key): string;
}