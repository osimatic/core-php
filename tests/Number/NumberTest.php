<?php

namespace Tests\Number;

use Osimatic\Number\Number;
use PHPUnit\Framework\TestCase;

final class NumberTest extends TestCase
{
	protected function setUp(): void
	{
		// Set default locale to English for consistent test results
		\Locale::setDefault('en_US');
	}

	/* ===================== Formatting ===================== */

	public function testAddLeadingZero(): void
	{
		// Basic padding
		$this->assertEquals('001', Number::addLeadingZero(1, 3));
		$this->assertEquals('0042', Number::addLeadingZero(42, 4));
		$this->assertEquals('100', Number::addLeadingZero(100, 3));

		// No padding needed
		$this->assertEquals('1234', Number::addLeadingZero(1234, 3));

		// With float (str_pad pads the whole string including decimal point)
		$this->assertEquals('03.14', Number::addLeadingZero(3.14, 5));
	}

	public function testFormat(): void
	{
		// Default 2 decimals
		$this->assertEquals('1,234.56', Number::format(1234.56));
		$this->assertEquals('1,000.00', Number::format(1000));

		// Custom decimals
		$this->assertEquals('1,234.6', Number::format(1234.567, 1));
		$this->assertEquals('1,235', Number::format(1234.567, 0));

		// Negative numbers
		$this->assertEquals('-1,234.56', Number::format(-1234.56));
	}

	public function testFormatInt(): void
	{
		$this->assertEquals('1,235', Number::formatInt(1234.56)); // Rounds to nearest int
		$this->assertEquals('1,000', Number::formatInt(1000));
		$this->assertEquals('-1,235', Number::formatInt(-1234.56));
	}

	public function testFormatOrdinal(): void
	{
		// English locale behavior
		$result = Number::formatOrdinal(1);
		$this->assertIsString($result);
		$this->assertNotEmpty($result);

		$result2 = Number::formatOrdinal(2);
		$this->assertIsString($result2);
		$this->assertNotEquals($result, $result2);
	}

	public function testFormatScientific(): void
	{
		$result = Number::formatScientific(1234567.89);
		$this->assertIsString($result);
		$this->assertMatchesRegularExpression('/[0-9]/', $result);

		// With custom decimals
		$result2 = Number::formatScientific(1234567.89, 4);
		$this->assertIsString($result2);
	}

	public function testFormatSpellOut(): void
	{
		$result = Number::formatSpellOut(42);
		$this->assertIsString($result);
		$this->assertNotEmpty($result);
	}

	public function testFormatHex(): void
	{
		$binary = "\x01\x02\x0A\xFF";
		$hex = Number::formatHex($binary);

		$this->assertIsString($hex);
		$this->assertStringContainsString('01', $hex);
		$this->assertStringContainsString('02', $hex);
		$this->assertStringContainsString('0a', $hex);
		$this->assertStringContainsString('ff', $hex);
	}

	/* ===================== Parsing ===================== */

	public function testParseFloat(): void
	{
		$this->assertEquals(3.14, Number::parseFloat('3.14'));
		$this->assertEquals(3.14, Number::parseFloat('3,14'));
		$this->assertEquals(1234.56, Number::parseFloat('1234.56'));
		$this->assertEquals(1234.56, Number::parseFloat(' 1234.56 '));
		$this->assertEquals(-42.5, Number::parseFloat('-42.5'));
		$this->assertEquals(42.0, Number::parseFloat('+42'));

		// Empty or null
		$this->assertEquals(0.0, Number::parseFloat(''));
		$this->assertEquals(0.0, Number::parseFloat(null));

		// Integer strings become floats
		$this->assertEquals(42.0, Number::parseFloat('42'));
	}

	public function testParseInt(): void
	{
		$this->assertEquals(42, Number::parseInt('42'));
		$this->assertEquals(1234, Number::parseInt('1234'));
		$this->assertEquals(1234, Number::parseInt(' 1234 '));
		$this->assertEquals(-42, Number::parseInt('-42'));
		$this->assertEquals(42, Number::parseInt('+42'));

		// Float strings are truncated
		$this->assertEquals(3, Number::parseInt('3.14'));
		$this->assertEquals(3, Number::parseInt('3,14'));

		// Empty or null
		$this->assertEquals(0, Number::parseInt(''));
		$this->assertEquals(0, Number::parseInt(null));
	}

	public function testParseMinorUnits(): void
	{
		// Comma and dot separators, default 2 decimals
		$this->assertSame(5000, Number::parseMinorUnits('50,00'));
		$this->assertSame(5000, Number::parseMinorUnits('50.00'));
		$this->assertSame(5050, Number::parseMinorUnits('50.5'));
		$this->assertSame(5050, Number::parseMinorUnits('50,5'));
		$this->assertSame(5000, Number::parseMinorUnits('50'));
		$this->assertSame(1, Number::parseMinorUnits('0,01'));
		$this->assertSame(0, Number::parseMinorUnits('0'));
		$this->assertSame(750, Number::parseMinorUnits('007,50'));
		$this->assertSame(99999999, Number::parseMinorUnits('999999,99'));

		// Surrounding spaces are ignored
		$this->assertSame(5000, Number::parseMinorUnits(' 50,00 '));

		// Invalid: negative, exponent, thousands separator, too many decimals, letters, empty
		$this->assertNull(Number::parseMinorUnits('-5'));
		$this->assertNull(Number::parseMinorUnits('1e3'));
		$this->assertNull(Number::parseMinorUnits('1 000'));
		$this->assertNull(Number::parseMinorUnits('1,234'));
		$this->assertNull(Number::parseMinorUnits('abc'));
		$this->assertNull(Number::parseMinorUnits(''));
		$this->assertNull(Number::parseMinorUnits('50,'));
		$this->assertNull(Number::parseMinorUnits(',50'));

		// Overflow beyond 18 digits
		$this->assertNull(Number::parseMinorUnits('9999999999999999999'));

		// Custom number of decimals
		$this->assertSame(1234, Number::parseMinorUnits('1,234', 3));
		$this->assertSame(120, Number::parseMinorUnits('12', 1));
		$this->assertNull(Number::parseMinorUnits('12', 0));
	}

	public function testFloatToString(): void
	{
		$this->assertEquals('3.14', Number::floatToString(3.14));
		$this->assertEquals('42.0', Number::floatToString(42.0));
		$this->assertEquals('0.0', Number::floatToString(null));
	}

	/* ===================== Validation ===================== */

	public function testCheckFloat(): void
	{
		// Valid floats
		$this->assertTrue(Number::checkFloat('3.14'));
		$this->assertTrue(Number::checkFloat('3,14'));
		$this->assertTrue(Number::checkFloat('42'));
		$this->assertTrue(Number::checkFloat('42.0'));
		$this->assertTrue(Number::checkFloat('-42.5'));
		$this->assertTrue(Number::checkFloat('+42.5'));

		// Negative allowed by default
		$this->assertTrue(Number::checkFloat('-42.5', true));
		$this->assertFalse(Number::checkFloat('-42.5', false));

		// Positive allowed by default
		$this->assertTrue(Number::checkFloat('42.5', true, true));
		$this->assertFalse(Number::checkFloat('42.5', true, false));

		// Invalid
		$this->assertFalse(Number::checkFloat('abc'));
		$this->assertFalse(Number::checkFloat('12.34.56'));
	}

	public function testCheckInt(): void
	{
		// Valid integers
		$this->assertTrue(Number::checkInt('42'));
		$this->assertTrue(Number::checkInt('-42'));
		$this->assertTrue(Number::checkInt('+42'));
		$this->assertTrue(Number::checkInt('0'));

		// Floats are not integers
		$this->assertFalse(Number::checkInt('3.14'));
		$this->assertFalse(Number::checkInt('3,14'));

		// Negative allowed by default
		$this->assertTrue(Number::checkInt('-42', true));
		$this->assertFalse(Number::checkInt('-42', false));

		// Positive allowed by default
		$this->assertTrue(Number::checkInt('42', true, true));
		$this->assertFalse(Number::checkInt('42', true, false));

		// Invalid
		$this->assertFalse(Number::checkInt('abc'));
		$this->assertFalse(Number::checkInt('12.34'));
	}

	/* ===================== Rounding ===================== */

	public function testFloatRoundUp(): void
	{
		// Basic rounding up
		$this->assertEquals(3.15, Number::floatRoundUp(3.141, 2));
		$this->assertEquals(3.142, Number::floatRoundUp(3.1415, 3));
		$this->assertEquals(3.2, Number::floatRoundUp(3.14, 1));

		// Already at precision
		$this->assertEquals(3.14, Number::floatRoundUp(3.14, 2));

		// Negative numbers
		$this->assertEquals(-3.14, Number::floatRoundUp(-3.141, 2));

		// Zero decimals
		$this->assertEquals(4.0, Number::floatRoundUp(3.1, 0));
	}

	public function testFloatRoundDown(): void
	{
		// Basic rounding down
		$this->assertEquals(3.14, Number::floatRoundDown(3.149, 2));
		$this->assertEquals(3.141, Number::floatRoundDown(3.1419, 3));
		$this->assertEquals(3.1, Number::floatRoundDown(3.19, 1));

		// Already at precision
		$this->assertEquals(3.14, Number::floatRoundDown(3.14, 2));

		// Negative numbers
		$this->assertEquals(-3.15, Number::floatRoundDown(-3.141, 2));

		// Zero decimals
		$this->assertEquals(3.0, Number::floatRoundDown(3.9, 0));
	}

	/* ===================== Type Checking ===================== */

	public function testIsInteger(): void
	{
		$this->assertTrue(Number::isInteger(42));
		$this->assertTrue(Number::isInteger(42.0));
		$this->assertTrue(Number::isInteger(-5));
		$this->assertTrue(Number::isInteger(0));

		$this->assertFalse(Number::isInteger(3.14));
		$this->assertFalse(Number::isInteger(42.1));
		$this->assertFalse(Number::isInteger(-5.5));
	}

	public function testIsFloat(): void
	{
		$this->assertTrue(Number::isFloat(3.14));
		$this->assertTrue(Number::isFloat(42.1));
		$this->assertTrue(Number::isFloat(-5.5));

		$this->assertFalse(Number::isFloat(42));
		$this->assertFalse(Number::isFloat(42.0));
		$this->assertFalse(Number::isFloat(-5));
		$this->assertFalse(Number::isFloat(0));
	}

	public function testGetNbDigitsOfInt(): void
	{
		$this->assertEquals(1, Number::getNbDigitsOfInt(0));
		$this->assertEquals(1, Number::getNbDigitsOfInt(5));
		$this->assertEquals(2, Number::getNbDigitsOfInt(42));
		$this->assertEquals(3, Number::getNbDigitsOfInt(123));
		$this->assertEquals(6, Number::getNbDigitsOfInt(112233));

		// Negative numbers (minus sign is counted)
		$this->assertEquals(2, Number::getNbDigitsOfInt(-5));
		$this->assertEquals(3, Number::getNbDigitsOfInt(-42));
	}

	/* ===================== Mathematics ===================== */

	public function testDecimal(): void
	{
		// Extract decimal part as float (use delta due to float precision limits)
		$this->assertEqualsWithDelta(0.3344, Number::decimal(1122.3344), 0.0001);
		$this->assertEqualsWithDelta(0.5, Number::decimal(3.5), 0.0001);
		$this->assertEqualsWithDelta(0.14, Number::decimal(3.14), 0.0001);

		// Integer returns 0
		$this->assertEquals(0.0, Number::decimal(42));
		$this->assertEquals(0.0, Number::decimal(0));

		// Negative numbers
		$this->assertEqualsWithDelta(0.5, Number::decimal(-3.5), 0.0001);
	}

	public function testDecimalPart(): void
	{
		// Extract decimal part as integer
		$this->assertEquals(3344, Number::decimalPart(1122.3344));
		$this->assertEquals(5, Number::decimalPart(3.5));
		$this->assertEquals(14, Number::decimalPart(3.14));

		// Integer returns 0
		$this->assertEquals(0, Number::decimalPart(42));
		$this->assertEquals(0, Number::decimalPart(0));

		// Negative numbers
		$this->assertEquals(5, Number::decimalPart(-3.5));
		$this->assertEquals(75, Number::decimalPart(-5.75));
	}

	public function testCheckLuhn(): void
	{
		// Valid Luhn numbers (common credit card test numbers)
		$this->assertTrue(Number::checkLuhn(79927398713)); // Valid
		$this->assertTrue(Number::checkLuhn(4532015112830366)); // Visa test
		$this->assertTrue(Number::checkLuhn(6011111111111117)); // Discover test

		// Invalid Luhn numbers
		$this->assertFalse(Number::checkLuhn(79927398712));
		$this->assertFalse(Number::checkLuhn(1234567890));
		$this->assertFalse(Number::checkLuhn(0));
	}

	/* ===================== Random ===================== */

	public function testGetRandomInt(): void
	{
		// Generate random integers
		$random = Number::getRandomInt(1, 10);
		$this->assertIsInt($random);
		$this->assertGreaterThanOrEqual(1, $random);
		$this->assertLessThanOrEqual(10, $random);

		// Same min/max returns that value
		$random = Number::getRandomInt(5, 5);
		$this->assertEquals(5, $random);

		// Negative range
		$random = Number::getRandomInt(-10, -1);
		$this->assertGreaterThanOrEqual(-10, $random);
		$this->assertLessThanOrEqual(-1, $random);
	}

	public function testGetRandomFloat(): void
	{
		// Generate random floats
		$random = Number::getRandomFloat(1.0, 10.0);
		$this->assertIsFloat($random);
		$this->assertGreaterThanOrEqual(1.0, $random);
		$this->assertLessThanOrEqual(10.0, $random);

		// With rounding
		$random = Number::getRandomFloat(1.0, 10.0, 2);
		$this->assertIsFloat($random);
		$this->assertGreaterThanOrEqual(1.0, $random);
		$this->assertLessThanOrEqual(10.0, $random);

		// Invalid range (min > max)
		$random = Number::getRandomFloat(10.0, 1.0);
		$this->assertFalse($random);
	}

	/* ===================== Formatting with French Locale ===================== */

	public function testFormatWithFrenchLocale(): void
	{
		// Change to French locale
		\Locale::setDefault('fr_FR');

		// French format uses space as thousands separator and comma as decimal separator
		$this->assertEquals('1 234,56', Number::format(1234.56));
		$this->assertEquals('1 000,00', Number::format(1000));

		// Custom decimals
		$this->assertEquals('1 234,6', Number::format(1234.567, 1));
		$this->assertEquals('1 235', Number::format(1234.567, 0));

		// Negative numbers
		$this->assertEquals('-1 234,56', Number::format(-1234.56));

		// Restore English locale
		\Locale::setDefault('en_US');
	}

	public function testFormatIntWithFrenchLocale(): void
	{
		// Change to French locale
		\Locale::setDefault('fr_FR');

		$this->assertEquals('1 235', Number::formatInt(1234.56)); // Rounds to nearest int
		$this->assertEquals('1 000', Number::formatInt(1000));
		$this->assertEquals('-1 235', Number::formatInt(-1234.56));

		// Restore English locale
		\Locale::setDefault('en_US');
	}
}