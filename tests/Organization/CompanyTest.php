<?php

declare(strict_types=1);

namespace Tests\Organization;

use Osimatic\Organization\Company;
use PHPUnit\Framework\TestCase;

final class CompanyTest extends TestCase
{
	// ========================================
	// Company Name & Number Tests
	// ========================================

	public function testIsValidCompanyName(): void
	{
		// Valid names
		$this->assertTrue(Company::isValidCompanyName('ACME Corporation'));
		$this->assertTrue(Company::isValidCompanyName('Société Martin & Fils'));
		$this->assertTrue(Company::isValidCompanyName('ABC-123'));
		$this->assertTrue(Company::isValidCompanyName("L'Entreprise"));
		$this->assertTrue(Company::isValidCompanyName('Café Müller'));
		$this->assertTrue(Company::isValidCompanyName('Company (France)'));
		$this->assertTrue(Company::isValidCompanyName('A.B.C. S.A.'));

		// Min length
		$this->assertTrue(Company::isValidCompanyName('ABC'));
		$this->assertFalse(Company::isValidCompanyName('AB'));

		// Max length
		$this->assertTrue(Company::isValidCompanyName(str_repeat('a', 100)));
		$this->assertFalse(Company::isValidCompanyName(str_repeat('a', 101)));

		// Invalid names
		$this->assertFalse(Company::isValidCompanyName(''));
		$this->assertFalse(Company::isValidCompanyName('A'));
		$this->assertFalse(Company::isValidCompanyName('Company@mail'));
	}

	public function testIsValidCompanyNumber(): void
	{
		// France: valid SIREN
		$this->assertTrue(Company::isValidCompanyNumber('FR', '732829320'));

		// France: invalid SIREN
		$this->assertFalse(Company::isValidCompanyNumber('FR', '123456789'));
		$this->assertFalse(Company::isValidCompanyNumber('FR', ''));

		// Other countries: always valid
		$this->assertTrue(Company::isValidCompanyNumber('US', '123456789'));
		$this->assertTrue(Company::isValidCompanyNumber('DE', 'DE123456789'));
	}

	// ========================================
	// France Tests
	// ========================================

	public function testParseFranceSiren(): void
	{
		$this->assertSame('732829320', Company::parseFranceSiren('732829320'));
		$this->assertSame('732829320', Company::parseFranceSiren('732 829 320'));
		$this->assertSame('732829320', Company::parseFranceSiren(" 732\t829\n320 "));
		$this->assertSame('', Company::parseFranceSiren(''));
		$this->assertSame('', Company::parseFranceSiren('   '));
	}

	public function testParseFranceSiret(): void
	{
		$this->assertSame('73282932000074', Company::parseFranceSiret('73282932000074'));
		$this->assertSame('73282932000074', Company::parseFranceSiret('732 829 320 00074'));
		$this->assertSame('73282932000074', Company::parseFranceSiret(" 732\t829 320\n00074 "));
		$this->assertSame('', Company::parseFranceSiret(''));
		$this->assertSame('', Company::parseFranceSiret('   '));
	}

	public function testIsValidFranceSiren(): void
	{
		// Valid SIREN
		$this->assertTrue(Company::isValidFranceSiren('732829320'));
		$this->assertTrue(Company::isValidFranceSiren('552100554'));

		// Invalid format
		$this->assertFalse(Company::isValidFranceSiren('12345678'));
		$this->assertFalse(Company::isValidFranceSiren('1234567890'));
		$this->assertFalse(Company::isValidFranceSiren('ABC123456'));
		$this->assertFalse(Company::isValidFranceSiren('732 829 320'));
		$this->assertFalse(Company::isValidFranceSiren(''));

		// Invalid Luhn check digit
		$this->assertFalse(Company::isValidFranceSiren('123456789'));
		$this->assertFalse(Company::isValidFranceSiren('111111111'));
	}

	public function testIsValidFranceSiret(): void
	{
		// Valid SIRET
		$this->assertTrue(Company::isValidFranceSiret('73282932000074'));

		// Invalid format
		$this->assertFalse(Company::isValidFranceSiret('7328293200007'));
		$this->assertFalse(Company::isValidFranceSiret('732829320000745'));
		$this->assertFalse(Company::isValidFranceSiret('ABC1234567890'));
		$this->assertFalse(Company::isValidFranceSiret('732 829 320 00074'));
		$this->assertFalse(Company::isValidFranceSiret(''));

		// Invalid SIREN
		$this->assertFalse(Company::isValidFranceSiret('12345678900001'));

		// Valid SIREN but invalid SIRET Luhn check digit
		$this->assertFalse(Company::isValidFranceSiret('73282932000075'));
	}

	public function testFormatFranceSiren(): void
	{
		// 9 digits are grouped by 3
		$this->assertSame('732 829 320', Company::formatFranceSiren('732829320'));

		// Already formatted or containing whitespace
		$this->assertSame('732 829 320', Company::formatFranceSiren('732 829 320'));
		$this->assertSame('732 829 320', Company::formatFranceSiren(" 732829 320\t"));

		// Not a 9-digit number: cleaned input returned unchanged
		$this->assertSame('12345678', Company::formatFranceSiren('12345678'));
		$this->assertSame('1234567890', Company::formatFranceSiren('123 456 7890'));
		$this->assertSame('73282932000074', Company::formatFranceSiren('73282932000074'));
		$this->assertSame('', Company::formatFranceSiren(''));
	}

	public function testFormatFranceSiret(): void
	{
		// 14 digits are grouped as SIREN (by 3) followed by the 5-digit NIC
		$this->assertSame('732 829 320 00074', Company::formatFranceSiret('73282932000074'));

		// Already formatted or containing whitespace
		$this->assertSame('732 829 320 00074', Company::formatFranceSiret('732 829 320 00074'));
		$this->assertSame('732 829 320 00074', Company::formatFranceSiret(" 73282932000074\n"));

		// Not a 14-digit number: cleaned input returned unchanged
		$this->assertSame('7328293200007', Company::formatFranceSiret('7328293200007'));
		$this->assertSame('732829320', Company::formatFranceSiret('732 829 320'));
		$this->assertSame('', Company::formatFranceSiret(''));
	}

	public function testGetFranceApeCodeList(): void
	{
		$list = Company::getFranceApeCodeList();
		$this->assertIsArray($list);
		$this->assertNotEmpty($list);
		$this->assertArrayHasKey('0111Z', $list);
	}

	public function testIsValidFranceCodeApe(): void
	{
		$this->assertTrue(Company::isValidFranceCodeApe('01.11Z'));
		$this->assertTrue(Company::isValidFranceCodeApe('0111Z'));
		$this->assertFalse(Company::isValidFranceCodeApe('99.99Z'));
		$this->assertFalse(Company::isValidFranceCodeApe(''));
	}

	public function testIsValidFranceCodeNaf(): void
	{
		// Valid codes, with or without dot
		$this->assertTrue(Company::isValidFranceCodeNaf('01.11Z'));
		$this->assertTrue(Company::isValidFranceCodeNaf('0111Z'));
		$this->assertTrue(Company::isValidFranceCodeNaf('62.01Z'));
		$this->assertTrue(Company::isValidFranceCodeNaf('47.11F'));

		// Invalid format
		$this->assertFalse(Company::isValidFranceCodeNaf(''));
		$this->assertFalse(Company::isValidFranceCodeNaf('1234'));
		$this->assertFalse(Company::isValidFranceCodeNaf('123456'));
		$this->assertFalse(Company::isValidFranceCodeNaf('AB.CD'));

		// Unknown codes
		$this->assertFalse(Company::isValidFranceCodeNaf('99.99Z'));
		$this->assertFalse(Company::isValidFranceCodeNaf('00.00A'));
	}

	public function testGetFranceApeLabel(): void
	{
		// Known code, with or without dot
		$label = Company::getFranceApeLabel('01.11Z');
		$this->assertIsString($label);
		$this->assertNotEmpty($label);
		$this->assertSame($label, Company::getFranceApeLabel('0111Z'));

		// Unknown code
		$this->assertSame('', Company::getFranceApeLabel('99.99Z'));
		$this->assertSame('', Company::getFranceApeLabel(''));
	}

	public function testFormatFranceRcs(): void
	{
		$rcs = Company::formatFranceRcs('73282932000074');
		$this->assertIsString($rcs);
		$this->assertSame('B 732 829 320', $rcs);
	}

	// ========================================
	// Monaco Tests
	// ========================================

	public function testIsValidMonacoNis(): void
	{
		// Valid
		$this->assertTrue(Company::isValidMonacoNis('12345'));
		$this->assertTrue(Company::isValidMonacoNis('ABCDE'));
		$this->assertTrue(Company::isValidMonacoNis('A1B2C'));
		$this->assertTrue(Company::isValidMonacoNis('1234567890'));

		// Invalid length
		$this->assertFalse(Company::isValidMonacoNis('1234'));
		$this->assertFalse(Company::isValidMonacoNis('12345678901'));

		// Invalid characters
		$this->assertFalse(Company::isValidMonacoNis('ABC-12'));
		$this->assertFalse(Company::isValidMonacoNis('ABC@12'));
		$this->assertFalse(Company::isValidMonacoNis(''));

		// Only uppercase letters are accepted
		$this->assertFalse(Company::isValidMonacoNis('abcde'));
	}

	// ========================================
	// Deprecated Methods Tests
	// ========================================

	public function testCheckCompanyName(): void
	{
		$this->assertTrue(Company::checkCompanyName('ACME Corporation'));
		$this->assertFalse(Company::checkCompanyName('AB'));
	}

	public function testCheckCompanyNumber(): void
	{
		$this->assertTrue(Company::checkCompanyNumber('FR', '732829320'));
		$this->assertFalse(Company::checkCompanyNumber('FR', '123456789'));
		$this->assertTrue(Company::checkCompanyNumber('US', '123456789'));
	}

	public function testCheckFranceSiren(): void
	{
		$this->assertTrue(Company::checkFranceSiren('732829320'));
		$this->assertFalse(Company::checkFranceSiren('123456789'));
	}

	public function testCheckFranceSiret(): void
	{
		$this->assertTrue(Company::checkFranceSiret('73282932000074'));
		$this->assertFalse(Company::checkFranceSiret('73282932000075'));
	}

	public function testCheckFranceCodeApe(): void
	{
		$this->assertTrue(Company::checkFranceCodeApe('01.11Z'));
		$this->assertFalse(Company::checkFranceCodeApe('99.99Z'));
	}

	public function testCheckFranceCodeNaf(): void
	{
		$this->assertTrue(Company::checkFranceCodeNaf('01.11Z'));
		$this->assertFalse(Company::checkFranceCodeNaf('99.99Z'));
	}

	public function testCheckMonacoNis(): void
	{
		$this->assertTrue(Company::checkMonacoNis('12345'));
		$this->assertFalse(Company::checkMonacoNis('1234'));
	}
}