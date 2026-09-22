<?php

namespace Osimatic\FileSystem;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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

	public function write(string $key, string $localFilePath, bool $public = true): bool
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

	public function upload(string $key, InputFile|UploadedFile $uploadedFile, bool $public = true): bool
	{
		$localFilePath = sys_get_temp_dir().'/'.uniqid('upload_', true);
		if (!File::moveUploadedFile($uploadedFile, $localFilePath, $this->logger)) {
			return false;
		}

		$isWritten = $this->write($key, $localFilePath, $public);
		unlink($localFilePath);

		return $isWritten;
	}

	public function read(string $key): ?string
	{
		$path = $this->getPath($key);
		if (!file_exists($path)) {
			return null;
		}

		$content = file_get_contents($path);
		if (false === $content) {
			$this->logger->error('Failed to read file from local storage.', [
				'key' => $key,
				'path' => $path,
			]);
			return null;
		}

		return $content;
	}

	public function metadata(string $key): ?array
	{
		$path = $this->getPath($key);
		if (!file_exists($path)) {
			return null;
		}

		return [
			'size' => filesize($path),
			'mimeType' => File::getMimeTypeForFile($path),
		];
	}

	public function exists(string $key): bool
	{
		return file_exists($this->getPath($key));
	}

	public function copy(string $sourceKey, string $destinationKey): bool
	{
		$sourcePath = $this->getPath($sourceKey);
		if (!file_exists($sourcePath)) {
			return false;
		}

		$destinationPath = $this->getPath($destinationKey);
		FileSystem::createDirectories($destinationPath);

		if (!copy($sourcePath, $destinationPath)) {
			$this->logger->error('Failed to copy file within local storage.', [
				'sourceKey' => $sourceKey,
				'destinationKey' => $destinationKey,
			]);
			return false;
		}

		return true;
	}

	public function rename(string $sourceKey, string $destinationKey): bool
	{
		$sourcePath = $this->getPath($sourceKey);
		if (!file_exists($sourcePath)) {
			return false;
		}

		$destinationPath = $this->getPath($destinationKey);
		FileSystem::createDirectories($destinationPath);

		if (!rename($sourcePath, $destinationPath)) {
			$this->logger->error('Failed to rename file within local storage.', [
				'sourceKey' => $sourceKey,
				'destinationKey' => $destinationKey,
			]);
			return false;
		}

		return true;
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

	public function getTemporaryUrl(string $key, \DateTimeImmutable $expiration): string
	{
		return $this->getUrl($key);
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