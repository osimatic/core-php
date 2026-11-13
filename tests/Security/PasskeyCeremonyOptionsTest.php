<?php

namespace Tests\Security;

use Osimatic\Security\PasskeyCeremonyOptions;
use PHPUnit\Framework\TestCase;

class PasskeyCeremonyOptionsTest extends TestCase
{
	// ========================================
	// Constructor & Configuration Tests
	// ========================================

	public function testConstructor(): void
	{
		$options = new \stdClass();
		$ceremonyOptions = new PasskeyCeremonyOptions($options, "\x00challenge\xff");
		self::assertSame($options, $ceremonyOptions->getOptions());
		self::assertSame("\x00challenge\xff", $ceremonyOptions->getChallenge());
	}

	// ========================================
	// Public Methods Tests
	// ========================================

	public function testGetOptions(): void
	{
		$options = (object)['publicKey' => (object)['rpId' => 'example.com']];
		self::assertSame($options, (new PasskeyCeremonyOptions($options, 'challenge'))->getOptions());
	}

	public function testGetChallenge(): void
	{
		self::assertSame("\x01\x02\xfe", (new PasskeyCeremonyOptions(new \stdClass(), "\x01\x02\xfe"))->getChallenge());
	}

	public function testToJson(): void
	{
		$options = (object)['publicKey' => (object)['rpId' => 'example.com', 'timeout' => 60000]];
		self::assertSame('{"publicKey":{"rpId":"example.com","timeout":60000}}', (new PasskeyCeremonyOptions($options, 'challenge'))->toJson());

		// Options that cannot be encoded
		try {
			(new PasskeyCeremonyOptions((object)['invalid' => "\xff"], 'challenge'))->toJson();
			self::fail('Expected exception for options that cannot be encoded');
		} catch (\JsonException) {
			self::assertTrue(true);
		}
	}
}
