<?php

namespace Osimatic\FileSystem;

use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\S3Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * File storage implementation backed by an AWS S3 bucket, exposed as a public-read bucket.
 * @link https://async-aws.com/clients/s3.html
 */
class S3FileStorage implements FileStorageInterface
{
	// ========== Constructor ==========

	/**
	 * @param S3Client $client The configured AsyncAws S3 client
	 * @param string $bucket The name of the S3 bucket used for storage
	 * @param string $region The AWS region of the bucket, used to build public URLs
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 */
	public function __construct(
		private readonly S3Client $client,
		private readonly string $bucket,
		private readonly string $region,
		private readonly LoggerInterface $logger = new NullLogger(),
	) {}

	// ========== Public Methods ==========

	public function write(string $key, string $localFilePath, bool $public = true): bool
	{
		try {
			$this->client->putObject([
				'Bucket' => $this->bucket,
				'Key' => $key,
				'Body' => fopen($localFilePath, 'rb'),
				'ACL' => $public ? 'public-read' : 'private',
			])->resolve();
		} catch (HttpException $e) {
			$this->logger->error('Failed to write file to S3 storage.', [
				'bucket' => $this->bucket,
				'key' => $key,
				'localFilePath' => $localFilePath,
				'error' => $e->getMessage(),
			]);
			return false;
		}

		$this->logger->debug('File written to S3 storage.', [
			'bucket' => $this->bucket,
			'key' => $key,
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
		try {
			return $this->client->getObject([
				'Bucket' => $this->bucket,
				'Key' => $key,
			])->getBody()->getContentAsString();
		} catch (HttpException $e) {
			$this->logger->error('Failed to read file from S3 storage.', [
				'bucket' => $this->bucket,
				'key' => $key,
				'error' => $e->getMessage(),
			]);
			return null;
		}
	}

	public function metadata(string $key): ?array
	{
		try {
			$result = $this->client->headObject([
				'Bucket' => $this->bucket,
				'Key' => $key,
			]);

			return [
				'size' => $result->getContentLength(),
				'mimeType' => $result->getContentType(),
			];
		} catch (HttpException) {
			return null;
		}
	}

	public function exists(string $key): bool
	{
		return $this->client->objectExists([
			'Bucket' => $this->bucket,
			'Key' => $key,
		])->isSuccess();
	}

	public function copy(string $sourceKey, string $destinationKey): bool
	{
		try {
			$this->client->copyObject([
				'Bucket' => $this->bucket,
				'Key' => $destinationKey,
				'CopySource' => $this->bucket.'/'.implode('/', array_map('rawurlencode', explode('/', $sourceKey))),
			])->resolve();
		} catch (HttpException $e) {
			$this->logger->error('Failed to copy file within S3 storage.', [
				'bucket' => $this->bucket,
				'sourceKey' => $sourceKey,
				'destinationKey' => $destinationKey,
				'error' => $e->getMessage(),
			]);
			return false;
		}

		return true;
	}

	public function rename(string $sourceKey, string $destinationKey): bool
	{
		return $this->copy($sourceKey, $destinationKey) && $this->delete($sourceKey);
	}

	public function delete(string $key): bool
	{
		try {
			$this->client->deleteObject([
				'Bucket' => $this->bucket,
				'Key' => $key,
			])->resolve();
		} catch (HttpException $e) {
			$this->logger->error('Failed to delete file from S3 storage.', [
				'bucket' => $this->bucket,
				'key' => $key,
				'error' => $e->getMessage(),
			]);
			return false;
		}

		$this->logger->debug('File deleted from S3 storage.', [
			'bucket' => $this->bucket,
			'key' => $key,
		]);

		return true;
	}

	public function getTemporaryUrl(string $key, \DateTimeImmutable $expiration): string
	{
		return $this->client->presign(new GetObjectRequest([
			'Bucket' => $this->bucket,
			'Key' => $key,
		]), $expiration);
	}

	public function getUrl(string $key): string
	{
		$encodedKey = implode('/', array_map('rawurlencode', explode('/', $key)));

		return sprintf('https://%s.s3.%s.amazonaws.com/%s', $this->bucket, $this->region, $encodedKey);
	}

}