<?php

namespace Osimatic\FileSystem;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 */
	public function __construct(
		private readonly string $rootPath,
		private readonly string $baseUrl,
		private readonly LoggerInterface $logger = new NullLogger(),
	) {}

	// ========== Public Methods ==========

	public function write(string $key, string $localFilePath): bool
	{
		$destinationPath = $this->getPath($key);
		FileSystem::createDirectories($destinationPath);

		if (!copy($localFilePath, $destinationPath)) {
			$this->logger->error('Failed to copy file to local storage.', [
				'key' => $key,
				'localFilePath' => $localFilePath,
				'destinationPath' => $destinationPath,
			]);
			return false;
		}

		$this->logger->debug('File written to local storage.', [
			'key' => $key,
			'destinationPath' => $destinationPath,
		]);

		return true;
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

		$path = $this->getPath($key);
		if (!unlink($path)) {
			$this->logger->error('Failed to delete file from local storage.', [
				'key' => $key,
				'path' => $path,
			]);
			return false;
		}

		$this->logger->debug('File deleted from local storage.', [
			'key' => $key,
			'path' => $path,
		]);

		return true;
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