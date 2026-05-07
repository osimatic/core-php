<?php

namespace Tests\Calendar;

use Osimatic\Calendar\Date;
use Osimatic\Calendar\DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Date class - methods that do NOT take DateTime objects as parameters
 */
class DateTest extends TestCase
{
	// ========== Parsing Methods Tests ==========

	public function testParse(): void
	{
		$date = Date::parse('2024-01-15');
		$this->assertInstanceOf(\DateTime::class, $date);
		$this->assertEquals('2024-01-15', $date->format('Y-m-d'));

		$date = Date::parse('20240115');
		$this->assertInstanceOf(\DateTime::class, $date);
		$this->assertEquals('2024-01-15', $date->format('Y-m-d'));

		// ISO 8601 with timezone — must return the calendar date as-is, without timezone conversion
		$date = Date::parse('2024-01-15T00:00:00Z');
		$this->assertInstanceOf(\DateTime::class, $date);
		$this->assertEquals('2024-01-15', $date->format('Y-m-d'));

		$date = Date::parse('2024-01-15T00:00:00+05:30');
		$this->assertInstanceOf(\DateTime::class, $date);
		$this->assertEquals('2024-01-15', $date->format('Y-m-d'));

		$date = Date::parse('2024-01-15T00:00:00-04:00');
		$this->assertInstanceOf(\DateTime::class, $date);
		$this->assertEquals('2024-01-15', $date->format('Y-m-d'));

		$date = Date::parse('2024-01-15T23:59:59Z');
		$this->assertInstanceOf(\DateTime::class, $date);
		$this->assertEquals('2024-01-15', $date->format('Y-m-d'));

		$this->assertNull(Date::parse('invalid'));
		$this->assertNull(Date::parse(''));
	}

	public function testParseOrNull(): void
	{
		$date = Date::parseOrNull('2024-01-15');
		$this->assertInstanceOf(\DateTime::class, $date);

		$this->assertNull(Date::parseOrNull('invalid'));
	}

	public function testParseOrThrow(): void
	{
		$date = Date::parseOrThrow('2024-01-15');
		$this->assertInstanceOf(\DateTime::class, $date);

		$this->expectException(\InvalidArgumentException::class);
		Date::parseOrThrow('invalid');
	}

	// ========== Day Names Tests ==========

	public function testGetDayName(): void
	{
		// Test English locale
		$this->assertEquals('Monday', Date::getDayName(1, 'en_US'));
		$this->assertEquals('Sunday', Date::getDayName(7, 'en_US'));

		// Test French locale (ucfirst is applied)
		$this->assertEquals('Lundi', Date::getDayName(1, 'fr_FR'));
		$this->assertEquals('Dimanche', Date::getDayName(7, 'fr_FR'));

		// Invalid
		$this->assertEquals('', Date::getDayName(0));
		$this->assertEquals('', Date::getDayName(8));
		$this->assertEquals('', Date::getDayName(-1));
	}

	public function testGetDayNameShort(): void
	{
		// Test English locale
		$dayName = Date::getDayNameShort(1, 'en_US');
		$this->assertNotEmpty($dayName);
		$this->assertStringContainsString('Mon', $dayName);

		$dayName = Date::getDayNameShort(7, 'en_US');
		$this->assertNotEmpty($dayName);
		$this->assertStringContainsString('Sun', $dayName);

		// invalid
		$this->assertEquals('', Date::getDayNameShort(0));
		$this->assertEquals('', Date::getDayNameShort(8));
		$this->assertEquals('', Date::getDayNameShort(-1));
	}

	// ========== Month Names Tests ==========

	public function testGetMonthName(): void
	{
		// Test English locale
		$this->assertEquals('January', Date::getMonthName(1, 'en_US'));
		$this->assertEquals('December', Date::getMonthName(12, 'en_US'));

		// Test French locale (ucfirst is applied)
		$this->assertEquals('Janvier', Date::getMonthName(1, 'fr_FR'));
		$this->assertEquals('Décembre', Date::getMonthName(12, 'fr_FR'));

		// invalid
		$this->assertEquals('', Date::getMonthName(0));
		$this->assertEquals('', Date::getMonthName(13));
		$this->assertEquals('', Date::getMonthName(-1));
	}

	public function testGetMonthNameShort(): void
	{
		// Test English locale
		$monthName = Date::getMonthNameShort(1, 'en_US');
		$this->assertNotEmpty($monthName);
		$this->assertStringContainsString('Jan', $monthName);

		$monthName = Date::getMonthNameShort(12, 'en_US');
		$this->assertNotEmpty($monthName);
		$this->assertStringContainsString('Dec', $monthName);

		// invalid
		$this->assertEquals('', Date::getMonthNameShort(0));
		$this->assertEquals('', Date::getMonthNameShort(13));
		$this->assertEquals('', Date::getMonthNameShort(-1));
	}

	public function testGetMonthsInYearArray(): void
	{
		$months = Date::getMonthsInYearArray('en_US');
		$this->assertIsArray($months);
		$this->assertCount(12, $months);
		$this->assertEquals('January', $months[1]);
		$this->assertEquals('December', $months[12]);
	}

	// ========== Calendar Info Tests ==========

	public function testGetNumberOfDaysInMonth(): void
	{
		$this->assertEquals(31, Date::getNumberOfDaysInMonth(2024, 1)); // January
		$this->assertEquals(29, Date::getNumberOfDaysInMonth(2024, 2)); // February (leap year)
		$this->assertEquals(28, Date::getNumberOfDaysInMonth(2023, 2)); // February (non-leap year)
		$this->assertEquals(30, Date::getNumberOfDaysInMonth(2024, 4)); // April
		$this->assertEquals(31, Date::getNumberOfDaysInMonth(2024, 12)); // December
	}

	public function testGetDaysInMonthArray(): void
	{
		$days = Date::getDaysInMonthArray(2024, 2);
		$this->assertIsArray($days);
		$this->assertCount(29, $days); // 2024 is a leap year
		$this->assertInstanceOf(\DateTime::class, $days[0]);
		$this->assertEquals('2024-02-01', $days[0]->format('Y-m-d'));
		$this->assertEquals('2024-02-29', $days[28]->format('Y-m-d'));
	}

	public function testIsLeapYear(): void
	{
		$this->assertTrue(Date::isLeapYear(2024));
		$this->assertTrue(Date::isLeapYear(2000));
		$this->assertFalse(Date::isLeapYear(2023));
		$this->assertFalse(Date::isLeapYear(1900));
	}

	public function testGetNumberOfDaysInYear(): void
	{
		$this->assertEquals(366, Date::getNumberOfDaysInYear(2024)); // Leap year
		$this->assertEquals(365, Date::getNumberOfDaysInYear(2023)); // Non-leap year
		$this->assertEquals(366, Date::getNumberOfDaysInYear(2000)); // Leap year
		$this->assertEquals(365, Date::getNumberOfDaysInYear(1900)); // Not a leap year
	}

	public function testGetWeeksInYear(): void
	{
		$weeks2024 = Date::getWeeksInYear(2024);
		$this->assertGreaterThanOrEqual(52, $weeks2024);
		$this->assertLessThanOrEqual(53, $weeks2024);

		$weeks2023 = Date::getWeeksInYear(2023);
		$this->assertGreaterThanOrEqual(52, $weeks2023);
		$this->assertLessThanOrEqual(53, $weeks2023);
	}

	public function testGetRemainingDayTypeCountForMonth(): void
	{
		// January 15, 2024 (Monday) — 16 remaining days (Jan 16–31)
		// Jan 16=Tue, 17=Wed, 18=Thu, 19=Fri, 20=Sat, 21=Sun,
		// Jan 22=Mon, 23=Tue, 24=Wed, 25=Thu, 26=Fri, 27=Sat, 28=Sun,
		// Jan 29=Mon, 30=Tue, 31=Wed
		$result = Date::getRemainingDayTypeCountForMonth(new \DateTime('2024-01-15'));
		$this->assertIsArray($result);
		$this->assertEquals(2, $result[1]); // Monday: Jan 22, 29
		$this->assertEquals(3, $result[2]); // Tuesday: Jan 16, 23, 30
		$this->assertEquals(3, $result[3]); // Wednesday: Jan 17, 24, 31
		$this->assertEquals(2, $result[4]); // Thursday: Jan 18, 25
		$this->assertEquals(2, $result[5]); // Friday: Jan 19, 26
		$this->assertEquals(2, $result[6]); // Saturday: Jan 20, 27
		$this->assertEquals(2, $result[7]); // Sunday: Jan 21, 28
		$this->assertEquals(16, array_sum($result));

		// First day of month (Jan 1, 2024 = Monday) — 30 remaining days (Jan 2–31)
		// Mon: 8,15,22,29 — Tue: 2,9,16,23,30 — Wed: 3,10,17,24,31 — Thu: 4,11,18,25
		// Fri: 5,12,19,26 — Sat: 6,13,20,27 — Sun: 7,14,21,28
		$result = Date::getRemainingDayTypeCountForMonth(new \DateTime('2024-01-01'));
		$this->assertEquals(4, $result[1]); // Monday
		$this->assertEquals(5, $result[2]); // Tuesday
		$this->assertEquals(5, $result[3]); // Wednesday
		$this->assertEquals(4, $result[4]); // Thursday
		$this->assertEquals(4, $result[5]); // Friday
		$this->assertEquals(4, $result[6]); // Saturday
		$this->assertEquals(4, $result[7]); // Sunday
		$this->assertEquals(30, array_sum($result));

		// Day before last (Jan 30, 2024 = Tuesday) — 1 remaining day (Jan 31 = Wednesday)
		$result = Date::getRemainingDayTypeCountForMonth(new \DateTime('2024-01-30'));
		$this->assertCount(1, $result);
		$this->assertEquals(1, $result[3]); // Wednesday: Jan 31

		// Last day of month (Jan 31, 2024) — no remaining days
		$result = Date::getRemainingDayTypeCountForMonth(new \DateTime('2024-01-31'));
		$this->assertEmpty($result);

		// February in a leap year (Feb 14, 2024 = Wednesday) — 15 remaining days (Feb 15–29)
		// Feb 15=Thu, 16=Fri, 17=Sat, 18=Sun, 19=Mon, 20=Tue, 21=Wed,
		// Feb 22=Thu, 23=Fri, 24=Sat, 25=Sun, 26=Mon, 27=Tue, 28=Wed, 29=Thu
		$result = Date::getRemainingDayTypeCountForMonth(new \DateTime('2024-02-14'));
		$this->assertEquals(2, $result[1]); // Monday: Feb 19, 26
		$this->assertEquals(2, $result[2]); // Tuesday: Feb 20, 27
		$this->assertEquals(2, $result[3]); // Wednesday: Feb 21, 28
		$this->assertEquals(3, $result[4]); // Thursday: Feb 15, 22, 29
		$this->assertEquals(2, $result[5]); // Friday: Feb 16, 23
		$this->assertEquals(2, $result[6]); // Saturday: Feb 17, 24
		$this->assertEquals(2, $result[7]); // Sunday: Feb 18, 25
		$this->assertEquals(15, array_sum($result));
	}

	// ========== Validation Tests ==========

	public function testIsValid(): void
	{
		$this->assertTrue(Date::isValid('2024-01-15'));
		$this->assertTrue(Date::isValid('20240115'));
		$this->assertFalse(Date::isValid('invalid'));
		$this->assertFalse(Date::isValid(''));
		$this->assertFalse(Date::isValid('2024-13-01')); // Invalid month
	}

	public function testIsValidDate(): void
	{
		// Normal valid dates
		$this->assertTrue(Date::isValidDate(2024, 1, 15));
		$this->assertTrue(Date::isValidDate(2024, 12, 31));
		$this->assertTrue(Date::isValidDate(2024, 6, 30));

		// Leap year - February 29th
		$this->assertTrue(Date::isValidDate(2024, 2, 29));
		$this->assertTrue(Date::isValidDate(2000, 2, 29));

		// Edge cases - last day of months with 31 days
		$this->assertTrue(Date::isValidDate(2024, 1, 31));
		$this->assertTrue(Date::isValidDate(2024, 3, 31));
		$this->assertTrue(Date::isValidDate(2024, 5, 31));
		$this->assertTrue(Date::isValidDate(2024, 7, 31));
		$this->assertTrue(Date::isValidDate(2024, 8, 31));
		$this->assertTrue(Date::isValidDate(2024, 10, 31));
		$this->assertTrue(Date::isValidDate(2024, 12, 31));

		// Edge cases - last day of months with 30 days
		$this->assertTrue(Date::isValidDate(2024, 4, 30));
		$this->assertTrue(Date::isValidDate(2024, 6, 30));
		$this->assertTrue(Date::isValidDate(2024, 9, 30));
		$this->assertTrue(Date::isValidDate(2024, 11, 30));

		// Invalid months
		$this->assertFalse(Date::isValidDate(2024, 0, 15)); // Month 0
		$this->assertFalse(Date::isValidDate(2024, 13, 15)); // Month 13
		$this->assertFalse(Date::isValidDate(2024, -1, 15)); // Negative month

		// Invalid days
		$this->assertFalse(Date::isValidDate(2024, 1, 0)); // Day 0
		$this->assertFalse(Date::isValidDate(2024, 1, 32)); // January has 31 days
		$this->assertFalse(Date::isValidDate(2024, 4, 31)); // April has 30 days
		$this->assertFalse(Date::isValidDate(2024, 2, 30)); // February never has 30 days
		$this->assertFalse(Date::isValidDate(2024, 6, -1)); // Negative day

		// Leap year validation - February 29th
		$this->assertTrue(Date::isValidDate(2024, 2, 29)); // 2024 is leap year
		$this->assertTrue(Date::isValidDate(2000, 2, 29)); // 2000 is leap year

		// Non-leap years - February 29th should be invalid
		$this->assertFalse(Date::isValidDate(2023, 2, 29)); // 2023 is not leap year
		$this->assertFalse(Date::isValidDate(1900, 2, 29)); // 1900 is not leap year (divisible by 100 but not 400)
		$this->assertFalse(Date::isValidDate(2100, 2, 29)); // 2100 is not leap year

		// Non-leap years - February 28th should be valid
		$this->assertTrue(Date::isValidDate(2023, 2, 28));
		$this->assertTrue(Date::isValidDate(1900, 2, 28));

		// Very old and future years (checkdate supports 1 to 32767 in PHP)
		$this->assertTrue(Date::isValidDate(1, 1, 1)); // Year 1 AD
		$this->assertTrue(Date::isValidDate(9999, 12, 31)); // Far future
		$this->assertFalse(Date::isValidDate(50000, 12, 31)); // Too far future

		// Negative years should still work with checkdate
		$this->assertFalse(Date::isValidDate(-1, 1, 1)); // Negative year is not allowed
		$this->assertFalse(Date::isValidDate(-5000, 1, 1)); // Negative year too far
		$this->assertFalse(Date::isValidDate(-1, 2, 29)); // Negative year, invalid Feb 29
	}
}