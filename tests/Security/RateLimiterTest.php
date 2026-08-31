<?php

namespace Tests\Security;

use Osimatic\Security\RateLimiter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class RateLimiterTest extends TestCase
{
	private string $storageDirectory;
	private LoggerInterface $logger;

	protected function setUp(): void
	{
		$this->storageDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ratelimiter_test_' . uniqid();
		mkdir($this->storageDirectory);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	protected function tearDown(): void
	{
		foreach (glob($this->storageDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
			@unlink($file);
		}
		@rmdir($this->storageDirectory);
	}

	// ========================================
	// Constructor & Configuration Tests
	// ========================================

	public function testConstructor(): void
	{
		$rateLimiter = new RateLimiter();
		self::assertInstanceOf(RateLimiter::class, $rateLimiter);
	}

	public function testSetStorageDirectory(): void
	{
		$rateLimiter = new RateLimiter();
		$result = $rateLimiter->setStorageDirectory($this->storageDirectory);

		self::assertSame($rateLimiter, $result);
	}

	public function testSetCleanupProbability(): void
	{
		$rateLimiter = new RateLimiter();

		// Valid case
		$result = $rateLimiter->setCleanupProbability(50);
		self::assertSame($rateLimiter, $result);

		// Edge case: values below 1 are clamped to 1 instead of causing a division/range error in random_int()
		$result = $rateLimiter->setCleanupProbability(0);
		self::assertSame($rateLimiter, $result);
	}

	// ========================================
	// Public Methods Tests
	// ========================================

	public function testCheck(): void
	{
		$rateLimiter = new RateLimiter($this->storageDirectory, 1, $this->logger);

		// Requests under the limit are allowed
		self::assertTrue($rateLimiter->check('user-a', 3, 60));
		self::assertTrue($rateLimiter->check('user-a', 3, 60));
		self::assertTrue($rateLimiter->check('user-a', 3, 60));

		// The request that exceeds the limit is rejected
		self::assertFalse($rateLimiter->check('user-a', 3, 60));

		// Distinct keys have independent counters
		self::assertTrue($rateLimiter->check('user-b', 3, 60));
	}

	public function testCheckResetsCounterAfterWindowExpires(): void
	{
		$rateLimiter = new RateLimiter($this->storageDirectory, 1, $this->logger);

		self::assertTrue($rateLimiter->check('user-a', 1, -1));
		// The window (-1s) is already expired, so the counter resets instead of accumulating
		self::assertTrue($rateLimiter->check('user-a', 1, -1));
	}

	public function testCheckFailsOpenWhenStorageDirectoryIsNotWritable(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with(self::stringContains('failing open'), self::anything());

		$rateLimiter = new RateLimiter($this->storageDirectory . DIRECTORY_SEPARATOR . 'does-not-exist', 1, $this->logger);

		self::assertTrue($rateLimiter->check('user-a', 0, 60));
	}

	public function testCheckCleansUpStaleFiles(): void
	{
		// cleanupProbability = 1 makes the cleanup pass run on every call, so the test is deterministic
		$rateLimiter = new RateLimiter($this->storageDirectory, 1, $this->logger);

		// A counter whose window expired long ago (grace period is 1 hour)
		$staleFile = $this->storageDirectory . DIRECTORY_SEPARATOR . 'ratelimit_' . md5('stale-key') . '.json';
		file_put_contents($staleFile, json_encode(['count' => 1, 'reset' => time() - 7200]));

		// A counter that is still within its grace period must be kept
		$freshFile = $this->storageDirectory . DIRECTORY_SEPARATOR . 'ratelimit_' . md5('fresh-key') . '.json';
		file_put_contents($freshFile, json_encode(['count' => 1, 'reset' => time() + 60]));

		$rateLimiter->check('another-key', 10, 60);

		self::assertFileDoesNotExist($staleFile);
		self::assertFileExists($freshFile);
	}
}