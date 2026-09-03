<?php

namespace Osimatic\FileSystem;

use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\S3\S3Client;

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
	 */
	public function __construct(
		private readonly S3Client $client,
		private readonly string $bucket,
		private readonly string $region,
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
		} catch (HttpException) {
			return false;
		}

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
		} catch (HttpException) {
			return false;
		}

		return true;
	}

	public function getUrl(string $key): string
	{
		$encodedKey = implode('/', array_map('rawurlencode', explode('/', $key)));

		return sprintf('https://%s.s3.%s.amazonaws.com/%s', $this->bucket, $this->region, $encodedKey);
	}

}