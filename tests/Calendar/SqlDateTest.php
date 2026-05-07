<?php

namespace Tests\Calendar;

use Osimatic\Calendar\DateTime;
use Osimatic\Calendar\SqlDate;
use PHPUnit\Framework\TestCase;

final class SqlDateTest extends TestCase
{
	// ========== Parsing & Validation Methods Tests ==========

	public function testParse(): void
	{
		// Format ISO
		$this->assertEquals('2024-01-15', SqlDate::parse('2024-01-15'));

		// Format français
		$this->assertEquals('2024-01-15', SqlDate::parse('15/01/2024'));

		// Format avec texte
		$this->assertEquals('2024-12-25', SqlDate::parse('2024-12-25'));

		// Format array
		$this->assertEquals('2024-03-20', SqlDate::parse(['date' => '2024-03-20 12:30:45']));

		// Date invalide
		$this->assertNull(SqlDate::parse('invalid-date'));
		$this->assertNull(SqlDate::parse(''));
	}

	public function testIsValid(): void
	{
		// Dates valides
		$this->assertTrue(SqlDate::isValid('2024-01-15'));
		$this->assertTrue(SqlDate::isValid('2024-12-31'));
		$this->assertTrue(SqlDate::isValid('2024-02-29')); // Année bissextile

		// Dates invalides
		$this->assertFalse(SqlDate::isValid('2023-02-29')); // Pas année bissextile
		$this->assertFalse(SqlDate::isValid('2024-13-01')); // Mois invalide
		$this->assertFalse(SqlDate::isValid('2024-01-32')); // Jour invalide
		$this->assertFalse(SqlDate::isValid('2024-00-15')); // Mois 0
		$this->assertFalse(SqlDate::isValid('invalid'));
		$this->assertFalse(SqlDate::isValid(null));
	}

	public function testIsValidMethod(): void
	{
		// Dates valides
		$this->assertTrue(SqlDate::isValid('2024-01-15'));
		$this->assertTrue(SqlDate::isValid('2024-12-31'));
		$this->assertTrue(SqlDate::isValid('2024-02-29')); // Année bissextile

		// Dates invalides
		$this->assertFalse(SqlDate::isValid('2023-02-29')); // Pas année bissextile
		$this->assertFalse(SqlDate::isValid('2024-13-01')); // Mois invalide
		$this->assertFalse(SqlDate::isValid('2024-01-32')); // Jour invalide
		$this->assertFalse(SqlDate::isValid('2024-00-15')); // Mois 0
		$this->assertFalse(SqlDate::isValid('invalid'));
		$this->assertFalse(SqlDate::isValid(null));
	}

	// ========== Extraction Methods Tests ==========

	public function testGetYear(): void
	{
		$this->assertEquals(2024, SqlDate::getYear('2024-01-15'));
		$this->assertEquals(2023, SqlDate::getYear('2023-12-31'));
		$this->assertEquals(2000, SqlDate::getYear('2000-06-15'));
	}

	public function testGetMonth(): void
	{
		$this->assertEquals(1, SqlDate::getMonth('2024-01-15'));
		$this->assertEquals(12, SqlDate::getMonth('2024-12-31'));
		$this->assertEquals(6, SqlDate::getMonth('2024-06-15'));
	}

	public function testGetDay(): void
	{
		$this->assertEquals(15, SqlDate::getDay('2024-01-15'));
		$this->assertEquals(31, SqlDate::getDay('2024-12-31'));
		$this->assertEquals(1, SqlDate::getDay('2024-06-01'));
	}

	public function testGetDayOfWeek(): void
	{
		// 2024-01-15 est un lundi (1)
		$this->assertEquals(1, SqlDate::getDayOfWeek('2024-01-15'));

		// 2024-01-21 est un dimanche (7)
		$this->assertEquals(7, SqlDate::getDayOfWeek('2024-01-21'));

		// 2024-01-17 est un mercredi (3)
		$this->assertEquals(3, SqlDate::getDayOfWeek('2024-01-17'));
	}

	// ========== Creation Methods Tests ==========

	public function testCreate(): void
	{
		// Date normale
		$this->assertEquals('2024-01-15', SqlDate::create(2024, 1, 15));

		// Date avec zéros à gauche
		$this->assertEquals('2024-01-01', SqlDate::create(2024, 1, 1));
		$this->assertEquals('2024-12-31', SqlDate::create(2024, 12, 31));

		// Formatage correct des mois/jours < 10
		$this->assertEquals('2024-03-05', SqlDate::create(2024, 3, 5));
		$this->assertEquals('2024-10-25', SqlDate::create(2024, 10, 25));
	}

	public function testToday(): void
	{
		$today = SqlDate::today();
		$this->assertEquals(date('Y-m-d'), $today);
		$this->assertTrue(SqlDate::isValid($today));
	}

	public function testYesterday(): void
	{
		$yesterday = SqlDate::yesterday();
		$this->assertEquals(date('Y-m-d', strtotime('-1 day')), $yesterday);
		$this->assertTrue(SqlDate::isValid($yesterday));
	}

	public function testTomorrow(): void
	{
		$tomorrow = SqlDate::tomorrow();
		$this->assertEquals(date('Y-m-d', strtotime('+1 day')), $tomorrow);
		$this->assertTrue(SqlDate::isValid($tomorrow));
	}

	// ========== Conversion Methods Tests ==========

	public function testToDateTime(): void
	{
		$dateTime = SqlDate::toDateTime('2024-01-15');
		$this->assertInstanceOf(\DateTime::class, $dateTime);
		$this->assertEquals('2024-01-15', $dateTime->format('Y-m-d'));
		$this->assertEquals('00:00:00', $dateTime->format('H:i:s'));
	}

	public function testToTimestamp(): void
	{
		$timestamp = SqlDate::toTimestamp('2024-01-15');
		$this->assertIsInt($timestamp);
		$this->assertEquals('2024-01-15', date('Y-m-d', $timestamp));
		$this->assertEquals('00:00:00', date('H:i:s', $timestamp));
	}

	// ========== Calculation Methods Tests ==========

	public function testAddDays(): void
	{
		// Add 5 days
		$this->assertEquals('2024-01-20', SqlDate::addDays('2024-01-15', 5));

		// Add 0 days
		$this->assertEquals('2024-01-15', SqlDate::addDays('2024-01-15', 0));

		// Add negative days (same as subtract)
		$this->assertEquals('2024-01-10', SqlDate::addDays('2024-01-15', -5));

		// Cross month boundary
		$this->assertEquals('2024-02-03', SqlDate::addDays('2024-01-29', 5));

		// Cross year boundary
		$this->assertEquals('2025-01-05', SqlDate::addDays('2024-12-31', 5));
	}

	public function testSubDays(): void
	{
		// Subtract 5 days
		$this->assertEquals('2024-01-10', SqlDate::subDays('2024-01-15', 5));

		// Subtract 0 days
		$this->assertEquals('2024-01-15', SqlDate::subDays('2024-01-15', 0));

		// Cross month boundary
		$this->assertEquals('2024-01-27', SqlDate::subDays('2024-02-03', 7));

		// Cross year boundary
		$this->assertEquals('2023-12-27', SqlDate::subDays('2024-01-03', 7));
	}

	public function testAddMonths(): void
	{
		// Add 1 month
		$this->assertEquals('2024-02-15', SqlDate::addMonths('2024-01-15', 1));

		// Add 6 months
		$this->assertEquals('2024-07-15', SqlDate::addMonths('2024-01-15', 6));

		// Add 12 months (cross year)
		$this->assertEquals('2025-01-15', SqlDate::addMonths('2024-01-15', 12));

		// Add 0 months
		$this->assertEquals('2024-01-15', SqlDate::addMonths('2024-01-15', 0));
	}

	public function testSubMonths(): void
	{
		// Subtract 1 month
		$this->assertEquals('2023-12-15', SqlDate::subMonths('2024-01-15', 1));

		// Subtract 6 months
		$this->assertEquals('2023-07-15', SqlDate::subMonths('2024-01-15', 6));

		// Subtract 12 months (cross year)
		$this->assertEquals('2023-01-15', SqlDate::subMonths('2024-01-15', 12));

		// Subtract 0 months
		$this->assertEquals('2024-01-15', SqlDate::subMonths('2024-01-15', 0));
	}

	public function testAddYears(): void
	{
		// Add 1 year
		$this->assertEquals('2025-01-15', SqlDate::addYears('2024-01-15', 1));

		// Add 5 years
		$this->assertEquals('2029-01-15', SqlDate::addYears('2024-01-15', 5));

		// Add 0 years
		$this->assertEquals('2024-01-15', SqlDate::addYears('2024-01-15', 0));
	}

	public function testSubYears(): void
	{
		// Subtract 1 year
		$this->assertEquals('2023-01-15', SqlDate::subYears('2024-01-15', 1));

		// Subtract 5 years
		$this->assertEquals('2019-01-15', SqlDate::subYears('2024-01-15', 5));

		// Subtract 0 years
		$this->assertEquals('2024-01-15', SqlDate::subYears('2024-01-15', 0));
	}

	public function testGetDaysBetween(): void
	{
		// 5 days difference
		$this->assertEquals(5, SqlDate::getDaysBetween('2024-01-20', '2024-01-15'));

		// Same day
		$this->assertEquals(0, SqlDate::getDaysBetween('2024-01-15', '2024-01-15'));

		// Negative difference (first date is earlier)
		$this->assertEquals(-5, SqlDate::getDaysBetween('2024-01-15', '2024-01-20'));

		// Cross month boundary
		$this->assertEquals(31, SqlDate::getDaysBetween('2024-02-15', '2024-01-15'));

		// Cross year boundary
		$this->assertEquals(366, SqlDate::getDaysBetween('2025-01-15', '2024-01-15')); // 2024 is leap year
	}

	// ========== Comparison Methods Tests ==========

	public function testIsBefore(): void
	{
		// 2024-01-15 is before 2024-01-20
		$this->assertTrue(SqlDate::isBefore('2024-01-15', '2024-01-20'));

		// 2024-01-20 is not before 2024-01-15
		$this->assertFalse(SqlDate::isBefore('2024-01-20', '2024-01-15'));

		// Same date is not before
		$this->assertFalse(SqlDate::isBefore('2024-01-15', '2024-01-15'));

		// Cross year boundary
		$this->assertTrue(SqlDate::isBefore('2023-12-31', '2024-01-01'));
	}

	public function testIsAfter(): void
	{
		// 2024-01-20 is after 2024-01-15
		$this->assertTrue(SqlDate::isAfter('2024-01-20', '2024-01-15'));

		// 2024-01-15 is not after 2024-01-20
		$this->assertFalse(SqlDate::isAfter('2024-01-15', '2024-01-20'));

		// Same date is not after
		$this->assertFalse(SqlDate::isAfter('2024-01-15', '2024-01-15'));

		// Cross year boundary
		$this->assertTrue(SqlDate::isAfter('2024-01-01', '2023-12-31'));
	}

	public function testIsEqual(): void
	{
		// Same date
		$this->assertTrue(SqlDate::isEqual('2024-01-15', '2024-01-15'));

		// Different dates
		$this->assertFalse(SqlDate::isEqual('2024-01-15', '2024-01-16'));
		$this->assertFalse(SqlDate::isEqual('2024-01-15', '2024-02-15'));
		$this->assertFalse(SqlDate::isEqual('2024-01-15', '2023-01-15'));
	}

	// ========== Week Methods Tests ==========

	public function testGetFirstDayOfWeek(): void
	{
		// Semaine 1 de 2024 (année qui commence un lundi)
		$result = SqlDate::getFirstDayOfWeek(2024, 1);
		$this->assertEquals('2024-01-01', $result);

		// Semaine 2 de 2024
		$result = SqlDate::getFirstDayOfWeek(2024, 2);
		$this->assertEquals('2024-01-08', $result);

		// Semaine 10 de 2024
		$result = SqlDate::getFirstDayOfWeek(2024, 10);
		$this->assertMatchesRegularExpression('/2024-\d{2}-\d{2}/', $result);

		// Vérifier que le résultat est toujours un lundi
		$timestamp = strtotime($result);
		$this->assertEquals(1, (int) date('N', $timestamp)); // N=1 pour lundi
	}

	public function testGetLastDayOfWeek(): void
	{
		// Dernière jour de la semaine 1 de 2024
		$result = SqlDate::getLastDayOfWeek(2024, 1);
		$this->assertEquals('2024-01-07', $result);

		// Dernière jour de la semaine 2 de 2024
		$result = SqlDate::getLastDayOfWeek(2024, 2);
		$this->assertEquals('2024-01-14', $result);

		// Vérifier que le résultat est toujours un dimanche
		$timestamp = strtotime($result);
		$this->assertEquals(7, (int) date('N', $timestamp)); // N=7 pour dimanche
	}

	// ========== Month Methods Tests ==========

	public function testGetFirstDayOfMonth(): void
	{
		// Premier jour de janvier
		$this->assertEquals('2024-01-01', SqlDate::getFirstDayOfMonth(2024, 1));

		// Premier jour de février
		$this->assertEquals('2024-02-01', SqlDate::getFirstDayOfMonth(2024, 2));

		// Premier jour de décembre
		$this->assertEquals('2024-12-01', SqlDate::getFirstDayOfMonth(2024, 12));

		// Année bissextile
		$this->assertEquals('2024-02-01', SqlDate::getFirstDayOfMonth(2024, 2));

		// Année non bissextile
		$this->assertEquals('2023-02-01', SqlDate::getFirstDayOfMonth(2023, 2));
	}

	public function testGetLastDayOfMonth(): void
	{
		// Dernier jour de janvier (31)
		$this->assertEquals('2024-01-31', SqlDate::getLastDayOfMonth(2024, 1));

		// Dernier jour de février année bissextile (29)
		$this->assertEquals('2024-02-29', SqlDate::getLastDayOfMonth(2024, 2));

		// Dernier jour de février année non bissextile (28)
		$this->assertEquals('2023-02-28', SqlDate::getLastDayOfMonth(2023, 2));

		// Dernier jour d'avril (30)
		$this->assertEquals('2024-04-30', SqlDate::getLastDayOfMonth(2024, 4));

		// Dernier jour de décembre (31)
		$this->assertEquals('2024-12-31', SqlDate::getLastDayOfMonth(2024, 12));
	}

	// ========== Year Methods Tests ==========

	public function testGetFirstDayOfYear(): void
	{
		$this->assertEquals('2024-01-01', SqlDate::getFirstDayOfYear(2024));
		$this->assertEquals('2023-01-01', SqlDate::getFirstDayOfYear(2023));
		$this->assertEquals('2025-01-01', SqlDate::getFirstDayOfYear(2025));
	}

	public function testGetLastDayOfYear(): void
	{
		$this->assertEquals('2024-12-31', SqlDate::getLastDayOfYear(2024));
		$this->assertEquals('2023-12-31', SqlDate::getLastDayOfYear(2023));
		$this->assertEquals('2025-12-31', SqlDate::getLastDayOfYear(2025));
	}

	// ========== Formatting Methods Tests ==========

	public function testFormat(): void
	{
		$sqlDate = '2024-01-15';

		// fr_FR
		$this->assertEqualsIgnoringCase('15/01/2024', SqlDate::format($sqlDate, 'fr_FR'));
		$this->assertEqualsIgnoringCase('15/01/2024', SqlDate::format($sqlDate, 'en_GB'));
		$this->assertEqualsIgnoringCase('1/15/24', SqlDate::format($sqlDate, 'en_US'));
	}

	public function testFormatShort(): void
	{
		$sqlDate = '2024-01-15';

		$this->assertEqualsIgnoringCase('15/01/2024', SqlDate::formatShort($sqlDate, 'fr_FR'));
		$this->assertEqualsIgnoringCase('15/01/2024', SqlDate::formatShort($sqlDate, 'en_GB'));
		$this->assertEqualsIgnoringCase('1/15/24', SqlDate::formatShort($sqlDate, 'en_US'));

		// empty date
		$this->assertEquals('', SqlDate::formatShort(''));

		// invalid date
		$this->assertEquals('', SqlDate::formatShort('invalid'));
	}

	public function testFormatMedium(): void
	{
		$sqlDate = '2024-01-15';

		$this->assertEqualsIgnoringCase('15 janv. 2024', SqlDate::formatMedium($sqlDate, 'fr_FR'));
		$this->assertEqualsIgnoringCase('15 Jan 2024', SqlDate::formatMedium($sqlDate, 'en_GB'));
		$this->assertEqualsIgnoringCase('Jan 15, 2024', SqlDate::formatMedium($sqlDate, 'en_US'));

		// empty date
		$this->assertEquals('', SqlDate::formatMedium(''));

		// invalid date
		$this->assertEquals('', SqlDate::formatMedium('invalid'));
	}

	public function testFormatLong(): void
	{
		$sqlDate = '2024-01-15';

		$this->assertEqualsIgnoringCase('15 janvier 2024', SqlDate::formatLong($sqlDate, 'fr_FR'));
		$this->assertEqualsIgnoringCase('15 January 2024', SqlDate::formatLong($sqlDate, 'en_GB'));
		$this->assertEqualsIgnoringCase('January 15, 2024', SqlDate::formatLong($sqlDate, 'en_US'));

		// empty date
		$this->assertEquals('', SqlDate::formatMedium(''));

		// invalid date
		$this->assertEquals('', SqlDate::formatMedium('invalid'));
	}

	public function testFormatFull(): void
	{
		$sqlDate = '2024-01-15';

		$this->assertEqualsIgnoringCase('lundi 15 janvier 2024', SqlDate::formatFull($sqlDate, 'fr_FR'));
		$this->assertEqualsIgnoringCase('Monday 15 January 2024', SqlDate::formatFull($sqlDate, 'en_GB'));
		$this->assertEqualsIgnoringCase('Monday, January 15, 2024', SqlDate::formatFull($sqlDate, 'en_US'));

		// empty date
		$this->assertEquals('', SqlDate::formatFull(''));

		// invalid date
		$this->assertEquals('', SqlDate::formatFull('invalid'));
	}

	public function testFormatISO(): void
	{
		// SQL DATE is already ISO format, so should return as-is
		$this->assertEquals('2024-01-15', SqlDate::formatISO('2024-01-15'));
		$this->assertEquals('2023-12-31', SqlDate::formatISO('2023-12-31'));

		// empty date
		$this->assertEquals('', SqlDate::formatISO(''));

		// invalid date
		$this->assertEquals('', SqlDate::formatISO('invalid'));
	}
}