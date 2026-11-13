<?php

namespace Tests\Security;

use Osimatic\Security\PasskeyCredential;
use PHPUnit\Framework\TestCase;

class PasskeyCredentialTest extends TestCase
{
	// ========================================
	// Constructor & Configuration Tests
	// ========================================

	public function testConstructor(): void
	{
		// Valid case, default signature counter
		$before = new \DateTimeImmutable();
		$credential = new PasskeyCredential("\x01\x02\xff", 'PEM', 'user');
		self::assertSame("\x01\x02\xff", $credential->getCredentialId());
		self::assertSame('PEM', $credential->getPublicKeyPem());
		self::assertSame('user', $credential->getUserId());
		self::assertSame(0, $credential->getSignatureCounter());
		self::assertSame('', $credential->getName());
		self::assertGreaterThanOrEqual($before, $credential->getCreatedAt());
		self::assertNull($credential->getLastUsedAt());

		// Valid case, all arguments given
		$createdAt = new \DateTimeImmutable('2025-01-02 03:04:05');
		$lastUsedAt = new \DateTimeImmutable('2025-06-07 08:09:10');
		$credential = new PasskeyCredential('id', 'PEM', 'user', 7, 'My phone', $createdAt, $lastUsedAt);
		self::assertSame(7, $credential->getSignatureCounter());
		self::assertSame('My phone', $credential->getName());
		self::assertSame($createdAt, $credential->getCreatedAt());
		self::assertSame($lastUsedAt, $credential->getLastUsedAt());

		// Empty credential ID
		try {
			new PasskeyCredential('', 'PEM', 'user');
			self::fail('Expected exception for an empty credential ID');
		} catch (\InvalidArgumentException) {
			self::assertTrue(true);
		}

		// Empty public key
		try {
			new PasskeyCredential('id', '', 'user');
			self::fail('Expected exception for an empty public key');
		} catch (\InvalidArgumentException) {
			self::assertTrue(true);
		}

		// Empty user ID
		try {
			new PasskeyCredential('id', 'PEM', '');
			self::fail('Expected exception for an empty user ID');
		} catch (\InvalidArgumentException) {
			self::assertTrue(true);
		}

		// Negative signature counter
		try {
			new PasskeyCredential('id', 'PEM', 'user', -1);
			self::fail('Expected exception for a negative signature counter');
		} catch (\InvalidArgumentException) {
			self::assertTrue(true);
		}
	}

	// ========================================
	// Public Methods Tests
	// ========================================

	public function testGetCredentialId(): void
	{
		self::assertSame("\x00binary\xfe", (new PasskeyCredential("\x00binary\xfe", 'PEM', 'user'))->getCredentialId());
	}

	public function testGetPublicKeyPem(): void
	{
		self::assertSame("-----BEGIN PUBLIC KEY-----\nabc\n-----END PUBLIC KEY-----\n", (new PasskeyCredential('id', "-----BEGIN PUBLIC KEY-----\nabc\n-----END PUBLIC KEY-----\n", 'user'))->getPublicKeyPem());
	}

	public function testGetUserId(): void
	{
		self::assertSame("\x00user\xfe", (new PasskeyCredential('id', 'PEM', "\x00user\xfe"))->getUserId());
	}

	public function testGetSignatureCounter(): void
	{
		self::assertSame(0, (new PasskeyCredential('id', 'PEM', 'user'))->getSignatureCounter());
		self::assertSame(42, (new PasskeyCredential('id', 'PEM', 'user', 42))->getSignatureCounter());
	}

	public function testGetName(): void
	{
		self::assertSame('', (new PasskeyCredential('id', 'PEM', 'user'))->getName());
		self::assertSame('Laptop', (new PasskeyCredential('id', 'PEM', 'user', 0, 'Laptop'))->getName());
	}

	public function testGetCreatedAt(): void
	{
		// Defaults to now
		$before = new \DateTimeImmutable();
		$createdAt = (new PasskeyCredential('id', 'PEM', 'user'))->getCreatedAt();
		self::assertGreaterThanOrEqual($before, $createdAt);
		self::assertLessThanOrEqual(new \DateTimeImmutable(), $createdAt);

		// Explicit date
		$date = new \DateTimeImmutable('2025-01-02 03:04:05');
		self::assertSame($date, (new PasskeyCredential('id', 'PEM', 'user', 0, '', $date))->getCreatedAt());
	}

	public function testGetLastUsedAt(): void
	{
		self::assertNull((new PasskeyCredential('id', 'PEM', 'user'))->getLastUsedAt());

		$date = new \DateTimeImmutable('2025-01-02 03:04:05');
		self::assertSame($date, (new PasskeyCredential('id', 'PEM', 'user', 0, '', null, $date))->getLastUsedAt());
	}

	public function testWithSignatureCounter(): void
	{
		$credential = new PasskeyCredential('id', 'PEM', 'user', 1, 'Laptop');
		$updated = $credential->withSignatureCounter(5);

		// New instance carrying the new counter and the same identity and metadata
		self::assertNotSame($credential, $updated);
		self::assertSame(5, $updated->getSignatureCounter());
		self::assertSame('id', $updated->getCredentialId());
		self::assertSame('PEM', $updated->getPublicKeyPem());
		self::assertSame('user', $updated->getUserId());
		self::assertSame('Laptop', $updated->getName());
		self::assertSame($credential->getCreatedAt(), $updated->getCreatedAt());

		// Original instance untouched
		self::assertSame(1, $credential->getSignatureCounter());

		// Negative signature counter
		try {
			$credential->withSignatureCounter(-1);
			self::fail('Expected exception for a negative signature counter');
		} catch (\InvalidArgumentException) {
			self::assertTrue(true);
		}
	}

	public function testWithName(): void
	{
		$credential = new PasskeyCredential('id', 'PEM', 'user', 3, 'Old name');
		$updated = $credential->withName('New name');

		// New instance carrying the new name and the same other data
		self::assertNotSame($credential, $updated);
		self::assertSame('New name', $updated->getName());
		self::assertSame('id', $updated->getCredentialId());
		self::assertSame('user', $updated->getUserId());
		self::assertSame(3, $updated->getSignatureCounter());

		// Original instance untouched
		self::assertSame('Old name', $credential->getName());
	}

	public function testWithLastUsedAt(): void
	{
		$credential = new PasskeyCredential('id', 'PEM', 'user', 3, 'Laptop');
		$date = new \DateTimeImmutable('2025-06-07 08:09:10');
		$updated = $credential->withLastUsedAt($date);

		// New instance carrying the new date and the same other data
		self::assertNotSame($credential, $updated);
		self::assertSame($date, $updated->getLastUsedAt());
		self::assertSame('Laptop', $updated->getName());
		self::assertSame(3, $updated->getSignatureCounter());
		self::assertSame($credential->getCreatedAt(), $updated->getCreatedAt());

		// Original instance untouched
		self::assertNull($credential->getLastUsedAt());
	}
}