<?php

namespace Osimatic\FileSystem;

use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\S3\S3Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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

	public function write(string $key, string $localFilePath): bool
	{
		try {
			$this->client->putObject([
				'Bucket' => $this->bucket,
				'Key' => $key,
				'Body' => fopen($localFilePath, 'rb'),
				'ACL' => 'public-read',
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

	public function exists(string $key): bool
	{
		return $this->client->objectExists([
			'Bucket' => $this->bucket,
			'Key' => $key,
		])->isSuccess();
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

	public function getUrl(string $key): string
	{
		$encodedKey = implode('/', array_map('rawurlencode', explode('/', $key)));

		return sprintf('https://%s.s3.%s.amazonaws.com/%s', $this->bucket, $this->region, $encodedKey);
	}

}