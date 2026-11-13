<?php

declare(strict_types=1);

namespace Tests\Data;

use Osimatic\Data\Encoding;
use PHPUnit\Framework\TestCase;

class EncodingTest extends TestCase
{
	// ========================================
	// Base64URL Tests
	// ========================================

	public function testEncodeBase64URL(): void
	{
		// Empty string
		self::assertSame('', Encoding::encodeBase64URL(''));

		// No padding, whatever the length of the input
		self::assertSame('Zg', Encoding::encodeBase64URL('f'));
		self::assertSame('Zm8', Encoding::encodeBase64URL('fo'));
		self::assertSame('Zm9v', Encoding::encodeBase64URL('foo'));

		// "-" and "_" instead of "+" and "/" (RFC 4648 section 5)
		self::assertSame('-_-_', Encoding::encodeBase64URL("\xfb\xff\xbf"));
		self::assertSame('AQL_', Encoding::encodeBase64URL("\x01\x02\xff"));

		// Binary data
		self::assertSame('AAH-', Encoding::encodeBase64URL("\x00\x01\xfe"));
	}

	public function testDecodeBase64URL(): void
	{
		// Round trip, binary data included
		foreach (['', 'f', 'fo', 'foo', "\x00\x01\xfe", "\xfb\xff\xbf", random_bytes(33)] as $data) {
			self::assertSame($data, Encoding::decodeBase64URL(Encoding::encodeBase64URL($data)));
			self::assertSame($data, Encoding::decodeBase64URL(Encoding::encodeBase64URL($data), true));
		}

		// Strict mode accepts the optional padding
		self::assertSame('f', Encoding::decodeBase64URL('Zg==', true));
		self::assertSame('fo', Encoding::decodeBase64URL('Zm8=', true));

		// Strict mode rejects characters outside the Base64URL alphabet, standard Base64 "+" and "/" included
		self::assertFalse(Encoding::decodeBase64URL('AQL/', true));
		self::assertFalse(Encoding::decodeBase64URL('AAH+', true));
		self::assertFalse(Encoding::decodeBase64URL('Zm9v!', true));
		self::assertFalse(Encoding::decodeBase64URL('Zm 9v', true));
		self::assertFalse(Encoding::decodeBase64URL("Zm9v\n", true));
		self::assertFalse(Encoding::decodeBase64URL('Zg=a', true));
		self::assertFalse(Encoding::decodeBase64URL('=Zg', true));

		// Non strict mode silently discards invalid characters
		self::assertSame('foo', Encoding::decodeBase64URL('Zm9v!', false));
		self::assertSame('foo', Encoding::decodeBase64URL('Zm 9v'));
	}
}
