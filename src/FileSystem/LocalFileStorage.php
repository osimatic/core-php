<?php

namespace Osimatic\FileSystem;

/**
 * File storage implementation backed by the local filesystem (or a mounted network share).
 * Files are stored under a root directory and exposed through a base public URL.
 */
class LocalFileStorage implements FileStorageInterface
{
	// ========== Constructor ==========

	/**
	 * @param string $rootPath The root directory under which files are stored (e.g. value of DATA_FILES_PATH)
	 * @param string $baseUrl The base URL under which files are publicly exposed (e.g. value of DATA_FILES_URL)
	 */
	public function __construct(
		private readonly string $rootPath,
		private readonly string $baseUrl,
	) {}

	// ========== Public Methods ==========

	public function write(string $key, string $localFilePath): bool
	{
		$destinationPath = $this->getPath($key);
		FileSystem::createDirectories($destinationPath);

		return copy($localFilePath, $destinationPath);
	}

	public function exists(string $key): bool
	{
		return file_exists($this->getPath($key));
	}

	public function delete(string $key): bool
	{
		if (!$this->exists($key)) {
			return true;
		}

		return unlink($this->getPath($key));
	}

	public function getUrl(string $key): string
	{
		return rtrim($this->baseUrl, '/').'/'.ltrim($key, '/');
	}

	// ========== Helper Methods ==========

	/**
	 * Resolves the absolute local path corresponding to the given storage key.
	 * @param string $key The storage key
	 * @return string The formatted absolute local path
	 */
	private function getPath(string $key): string
	{
		return FileSystem::formatPath($this->rootPath.'/'.$key);
	}

}