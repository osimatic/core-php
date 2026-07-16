<?php

declare(strict_types=1);

namespace Tests\Messaging;

use Osimatic\Messaging\PhoneNumber;
use Osimatic\Messaging\PhoneNumberType;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
	/* ===================== formatNational() ===================== */

	public function testFormatNationalWithValidFrenchNumber(): void
	{
		$result = PhoneNumber::formatNational('0612345678', 'FR');
		$this->assertSame('06 12 34 56 78', $result);
	}

	public function testFormatNationalWithInternationalFormat(): void
	{
		$result = PhoneNumber::formatNational('+33612345678', 'FR');
		$this->assertSame('06 12 34 56 78', $result);
	}

	public function testFormatNationalWithNull(): void
	{
		$result = PhoneNumber::formatNational(null);
		$this->assertNull($result);
	}

	public function testFormatNationalWithInvalidNumber(): void
	{
		$result = PhoneNumber::formatNational('invalid', 'FR');
		$this->assertSame('invalid', $result);
	}

	public function testFormatNationalWithDifferentCountry(): void
	{
		$result = PhoneNumber::formatNational('02012345678', 'GB');
		$this->assertIsString($result);
	}

	/* ===================== formatInternational() ===================== */

	public function testFormatInternationalWithValidFrenchNumber(): void
	{
		$result = PhoneNumber::formatInternational('0612345678', 'FR');
		$this->assertSame('+33 6 12 34 56 78', $result);
	}

	public function testFormatInternationalWithAlreadyInternational(): void
	{
		$result = PhoneNumber::formatInternational('+33612345678', 'FR');
		$this->assertSame('+33 6 12 34 56 78', $result);
	}

	public function testFormatInternationalWithNull(): void
	{
		$result = PhoneNumber::formatInternational(null);
		$this->assertNull($result);
	}

	public function testFormatInternationalWithInvalidNumber(): void
	{
		$result = PhoneNumber::formatInternational('invalid', 'FR');
		$this->assertSame('invalid', $result);
	}

	public function testFormatInternationalWithDifferentCountry(): void
	{
		$result = PhoneNumber::formatInternational('02012345678', 'GB');
		$this->assertIsString($result);
	}

	/* ===================== format() ===================== */

	public function testFormatWithNationalFormat(): void
	{
		$result = PhoneNumber::format('0612345678', \libphonenumber\PhoneNumberFormat::NATIONAL, 'FR');
		$this->assertSame('06 12 34 56 78', $result);
	}

	public function testFormatWithInternationalFormat(): void
	{
		$result = PhoneNumber::format('0612345678', \libphonenumber\PhoneNumberFormat::INTERNATIONAL, 'FR');
		$this->assertSame('+33 6 12 34 56 78', $result);
	}

	public function testFormatWithE164Format(): void
	{
		$result = PhoneNumber::format('0612345678', \libphonenumber\PhoneNumberFormat::E164, 'FR');
		$this->assertSame('+33612345678', $result);
	}

	public function testFormatWithNull(): void
	{
		$result = PhoneNumber::format(null, \libphonenumber\PhoneNumberFormat::NATIONAL);
		$this->assertNull($result);
	}

	public function testFormatWithInvalidNumber(): void
	{
		$result = PhoneNumber::format('invalid', \libphonenumber\PhoneNumberFormat::NATIONAL, 'FR');
		$this->assertSame('invalid', $result);
	}

	/* ===================== parse() ===================== */

	public function testParseWithValidFrenchNumber(): void
	{
		$result = PhoneNumber::parse('0612345678', 'FR');
		$this->assertSame('+33612345678', $result);
	}

	public function testParseWithInternationalNumber(): void
	{
		$result = PhoneNumber::parse('+33612345678', 'FR');
		$this->assertSame('+33612345678', $result);
	}

	public function testParseWithNull(): void
	{
		$result = PhoneNumber::parse(null);
		$this->assertNull($result);
	}

	public function testParseWithInvalidNumber(): void
	{
		$result = PhoneNumber::parse('invalid', 'FR');
		$this->assertNull($result);
	}

	public function testParseWithDifferentCountry(): void
	{
		$result = PhoneNumber::parse('02012345678', 'GB');
		$this->assertIsString($result);
		$this->assertStringStartsWith('+44', $result);
	}

	/* ===================== parseList() ===================== */

	public function testParseListWithValidNumbers(): void
	{
		$phoneNumbers = ['0612345678', '0687654321'];
		$result = PhoneNumber::parseList($phoneNumbers, 'FR');
		$this->assertIsArray($result);
		$this->assertCount(2, $result);
		$this->assertSame('+33612345678', $result[0]);
		$this->assertSame('+33687654321', $result[1]);
	}

	public function testParseListWithEmptyStrings(): void
	{
		$phoneNumbers = ['0612345678', '', '0687654321', null];
		$result = PhoneNumber::parseList($phoneNumbers, 'FR');
		$this->assertCount(2, $result);
	}

	public function testParseListWithEmptyArray(): void
	{
		$result = PhoneNumber::parseList([], 'FR');
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/* ===================== isPossible() ===================== */

	public function testIsPossibleWithValidNumber(): void
	{
		$this->assertTrue(PhoneNumber::isPossible('0612345678', 'FR'));
		$this->assertTrue(PhoneNumber::isPossible('+33612345678', 'FR'));
	}

	public function testIsPossibleWithInvalidNumber(): void
	{
		$this->assertFalse(PhoneNumber::isPossible('123', 'FR'));
		$this->assertFalse(PhoneNumber::isPossible('invalid', 'FR'));
	}

	public function testIsPossibleWithNull(): void
	{
		$this->assertFalse(PhoneNumber::isPossible(null));
	}

	/* ===================== isValid() ===================== */

	public function testIsValidWithValidNumber(): void
	{
		$this->assertTrue(PhoneNumber::isValid('0612345678', 'FR'));
		$this->assertTrue(PhoneNumber::isValid('+33612345678', 'FR'));
	}

	public function testIsValidWithInvalidNumber(): void
	{
		$this->assertFalse(PhoneNumber::isValid('123', 'FR'));
		$this->assertFalse(PhoneNumber::isValid('invalid', 'FR'));
		$this->assertFalse(PhoneNumber::isValid('0000000000', 'FR'));
	}

	public function testIsValidWithNull(): void
	{
		$this->assertFalse(PhoneNumber::isValid(null));
	}

	public function testIsValidWithDifferentCountries(): void
	{
		$this->assertTrue(PhoneNumber::isValid('02012345678', 'GB'));
		$this->assertTrue(PhoneNumber::isValid('+442012345678', 'GB'));
	}

	/* ===================== getType() ===================== */

	public function testGetTypeWithMobileNumber(): void
	{
		$type = PhoneNumber::getType('0612345678', 'FR');
		$this->assertInstanceOf(PhoneNumberType::class, $type);
		$this->assertSame(PhoneNumberType::MOBILE, $type);
	}

	public function testGetTypeWithFixedLineNumber(): void
	{
		$type = PhoneNumber::getType('0123456789', 'FR');
		$this->assertInstanceOf(PhoneNumberType::class, $type);
		$this->assertSame(PhoneNumberType::FIXED_LINE, $type);
	}

	public function testGetTypeWithNull(): void
	{
		$type = PhoneNumber::getType(null);
		$this->assertNull($type);
	}

	public function testGetTypeWithInvalidNumber(): void
	{
		$type = PhoneNumber::getType('invalid', 'FR');
		$this->assertNull($type);
	}

	/* ===================== isMobile() ===================== */

	public function testIsMobileWithMobileNumber(): void
	{
		$this->assertTrue(PhoneNumber::isMobile('0612345678', 'FR'));
		$this->assertTrue(PhoneNumber::isMobile('+33612345678', 'FR'));
	}

	public function testIsMobileWithFixedLineNumber(): void
	{
		$this->assertFalse(PhoneNumber::isMobile('0123456789', 'FR'));
	}

	public function testIsMobileWithNull(): void
	{
		$this->assertFalse(PhoneNumber::isMobile(null));
	}

	/* ===================== isFixedLine() ===================== */

	public function testIsFixedLineWithFixedLineNumber(): void
	{
		$this->assertTrue(PhoneNumber::isFixedLine('0123456789', 'FR'));
	}

	public function testIsFixedLineWithMobileNumber(): void
	{
		$this->assertFalse(PhoneNumber::isFixedLine('0612345678', 'FR'));
	}

	public function testIsFixedLineWithNull(): void
	{
		$this->assertFalse(PhoneNumber::isFixedLine(null));
	}

	/* ===================== isPremium() ===================== */

	public function testIsPremiumWithPremiumNumber(): void
	{
		// French premium rate numbers start with 08
		$this->assertTrue(PhoneNumber::isPremium('0899123456', 'FR'));
	}

	public function testIsPremiumWithRegularNumber(): void
	{
		$this->assertFalse(PhoneNumber::isPremium('0612345678', 'FR'));
	}

	public function testIsPremiumWithNull(): void
	{
		$this->assertFalse(PhoneNumber::isPremium(null));
	}

	/* ===================== isTollFree() ===================== */

	public function testIsTollFreeWithTollFreeNumber(): void
	{
		// French toll-free numbers start with 0800
		$this->assertTrue(PhoneNumber::isTollFree('0800123456', 'FR'));
	}

	public function testIsTollFreeWithRegularNumber(): void
	{
		$this->assertFalse(PhoneNumber::isTollFree('0612345678', 'FR'));
	}

	public function testIsTollFreeWithNull(): void
	{
		$this->assertFalse(PhoneNumber::isTollFree(null));
	}

	/* ===================== getCountryIsoCode() ===================== */

	public function testGetCountryIsoCodeWithFrenchNumber(): void
	{
		$code = PhoneNumber::getCountryIsoCode('0612345678', 'FR');
		$this->assertSame('FR', $code);
	}

	public function testGetCountryIsoCodeWithInternationalNumber(): void
	{
		$code = PhoneNumber::getCountryIsoCode('+33612345678', 'FR');
		$this->assertSame('FR', $code);
	}

	public function testGetCountryIsoCodeWithBritishNumber(): void
	{
		$code = PhoneNumber::getCountryIsoCode('+442012345678', 'GB');
		$this->assertSame('GB', $code);
	}

	public function testGetCountryIsoCodeWithNull(): void
	{
		$code = PhoneNumber::getCountryIsoCode(null);
		$this->assertNull($code);
	}

	public function testGetCountryIsoCodeWithInvalidNumber(): void
	{
		$code = PhoneNumber::getCountryIsoCode('invalid', 'FR');
		$this->assertNull($code);
	}

	/* ===================== formatFromIvr() ===================== */

	public function testFormatFromIvr(): void
	{
		// Null input
		$this->assertNull(PhoneNumber::formatFromIvr(null));

		// Anonymous / empty markers
		$this->assertSame('', PhoneNumber::formatFromIvr(''));
		$this->assertSame('', PhoneNumber::formatFromIvr('0'));
		$this->assertSame('', PhoneNumber::formatFromIvr('Anonymous'));

		// 9-digit number without leading zero: trunk code '0' is added
		$this->assertSame('0612345678', PhoneNumber::formatFromIvr('612345678'));

		// More than 9 digits, no '+' or '0' prefix: treated as international, '00' is added
		$this->assertSame('0033612345678', PhoneNumber::formatFromIvr('33612345678'));

		// Already starting with '+33': left untouched
		$this->assertSame('+33612345678', PhoneNumber::formatFromIvr('+33612345678'));

		// Already starting with '0': left untouched
		$this->assertSame('0612345678', PhoneNumber::formatFromIvr('0612345678'));

		// Already starting with '+' for a non-French country code: must not get '00' prepended
		// Regression test: this used to incorrectly return '00+3222505044'
		$this->assertSame('+3222505044', PhoneNumber::formatFromIvr('+3222505044'));

		// French overseas territory number with '0' instead of '00' (Guadeloupe calling code is 590)
		$this->assertSame('+590590123456', PhoneNumber::formatFromIvr('0590590123456'));
	}

	/* ===================== formatForIvr() ===================== */

	public function testFormatForIvrWithNull(): void
	{
		$result = PhoneNumber::formatForIvr(null);
		$this->assertNull($result);
	}

	public function testFormatForIvrWithPlusPrefix(): void
	{
		$result = PhoneNumber::formatForIvr('+33612345678');
		$this->assertSame('0612345678', $result);
	}

	public function testFormatForIvrWithZeroZeroThirtyThree(): void
	{
		$result = PhoneNumber::formatForIvr('0033612345678', true);
		$this->assertSame('0612345678', $result);
	}

	public function testFormatForIvrWithZeroZeroThirtyThreeWithoutTrunk(): void
	{
		$result = PhoneNumber::formatForIvr('0033612345678', false);
		$this->assertSame('612345678', $result);
	}

	public function testFormatForIvrWithRegularNumber(): void
	{
		$result = PhoneNumber::formatForIvr('0612345678');
		$this->assertSame('0612345678', $result);
	}

	public function testFormatForIvrWithShortNumber(): void
	{
		// Test with number shorter than 5 digits after 0033
		$result = PhoneNumber::formatForIvr('00331234', false);
		$this->assertSame('1234', $result);
	}
}