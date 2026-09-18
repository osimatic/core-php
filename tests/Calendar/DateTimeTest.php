<?php

namespace Tests\Calendar;

use Osimatic\Calendar\DateTime;
use Osimatic\Calendar\SqlTime;
use PHPUnit\Framework\TestCase;

final class DateTimeTest extends TestCase
{
	/* ===================== Basic Methods ===================== */

	public function testGetCurrentDateTime(): void
	{
		$result = DateTime::getCurrentDateTime();
		$this->assertInstanceOf(\DateTime::class, $result);
	}

	public function testGetCurrentDate(): void
	{
		$result = DateTime::getCurrentDate();
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('00:00:00', $result->format('H:i:s'));
	}

	/* ===================== Creation Methods ===================== */

	public function testCreate(): void
	{
		$result = DateTime::create(2024, 1, 15, 14, 30, 45);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('2024-01-15', $result->format('Y-m-d'));
		$this->assertEquals('14:30:45', $result->format('H:i:s'));

		// Invalid date
		$this->assertNull(DateTime::create(2024, 13, 1));
	}

	public function testCreateDate(): void
	{
		$result = DateTime::createDate(2024, 1, 15);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('2024-01-15', $result->format('Y-m-d'));
		$this->assertEquals('00:00:00', $result->format('H:i:s'));
	}

	public function testCreateTime(): void
	{
		$result = DateTime::createTime(14, 30, 45);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('14:30:45', $result->format('H:i:s'));

		// Invalid time
		$this->assertNull(DateTime::createTime(25, 0, 0));
	}

	/* ===================== Parsing Methods ===================== */

	public function testParse(): void
	{
		$result = DateTime::parse('2024-01-15');
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('2024-01-15', $result->format('Y-m-d'));
	}

	public function testParseFromSqlDateTime(): void
	{
		// Format SQL standard
		$result = DateTime::parseFromSqlDateTime('2024-01-15 14:30:45');
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('2024-01-15', $result->format('Y-m-d'));
		$this->assertEquals('14:30:45', $result->format('H:i:s'));

		// Format invalide
		$this->assertNull(DateTime::parseFromSqlDateTime('invalid'));
	}

	public function testParseFromTimestamp(): void
	{
		$timestamp = 1705327845; // 2024-01-15 14:30:45 UTC
		$result = DateTime::parseFromTimestamp($timestamp);
		$this->assertInstanceOf(\DateTime::class, $result);
	}

	public function testParseFromYearMonthDay(): void
	{
		$result = DateTime::parseFromYearMonthDay(2024, 1, 15);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('2024-01-15', $result->format('Y-m-d'));

		// Date invalide
		$result = DateTime::parseFromYearMonthDay(2024, 13, 1);
		$this->assertNull($result);
	}

	/* ===================== Formatting Methods ===================== */

	public function testFormat(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');

		// Short format

		// fr_FR
		$formatted = DateTime::format($dateTime, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT, 'fr_FR');
		$this->assertEqualsIgnoringCase('15/01/2024 14:30', $formatted);

		// en_GB
		$formatted = DateTime::format($dateTime, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT, 'en_GB');
		$this->assertEqualsIgnoringCase('15/01/2024, 14:30', $formatted);

		// en_US
		$formatted = DateTime::format($dateTime, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT, 'en_US');
		$this->assertEqualsIgnoringCase('1/15/24, 2:30 PM', $formatted);

		// Medium format

		// fr_FR
		$formatted = DateTime::format($dateTime, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::MEDIUM, 'fr_FR');
		$this->assertEqualsIgnoringCase('15 janv. 2024, 14:30:45', $formatted);

		// en_GB
		$formatted = DateTime::format($dateTime, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::MEDIUM, 'en_GB');
		$this->assertEqualsIgnoringCase('15 Jan 2024, 14:30:45', $formatted);

		// en_US
		$formatted = DateTime::format($dateTime, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::MEDIUM, 'en_US');
		$this->assertEqualsIgnoringCase('Jan 15, 2024, 2:30:45 PM', $formatted);

		// Long format

		// fr_FR
		$formatted = DateTime::format($dateTime, \IntlDateFormatter::LONG, \IntlDateFormatter::LONG, 'fr_FR');
		$this->assertEqualsIgnoringCase('15 janvier 2024 à 14:30:45 UTC', $formatted);

		// en_GB
		$formatted = DateTime::format($dateTime, \IntlDateFormatter::LONG, \IntlDateFormatter::LONG, 'en_GB');
		$this->assertEqualsIgnoringCase('15 January 2024 at 14:30:45 UTC', $formatted);

		// en_US
		$formatted = DateTime::format($dateTime, \IntlDateFormatter::LONG, \IntlDateFormatter::LONG, 'en_US');
		$this->assertEqualsIgnoringCase('January 15, 2024 at 2:30:45 PM UTC', $formatted);

		// Full format

		// fr_FR
		$formatted = DateTime::format($dateTime, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, 'fr_FR');
		$this->assertEqualsIgnoringCase('lundi 15 janvier 2024 à 14:30:45 temps universel coordonné', $formatted);

		// en_GB
		$formatted = DateTime::format($dateTime, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, 'en_GB');
		$this->assertEqualsIgnoringCase('Monday 15 January 2024 at 14:30:45 Coordinated Universal Time', $formatted);

		// en_US
		$formatted = DateTime::format($dateTime, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, 'en_US');
		$this->assertEqualsIgnoringCase('Monday, January 15, 2024 at 2:30:45 PM Coordinated Universal Time', $formatted);
	}

	public function testFormatDateTime(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');

		// Short format

		// fr_FR
		$formatted = DateTime::formatDateTime($dateTime, 'fr_FR', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
		$this->assertEqualsIgnoringCase('15/01/2024 14:30', $formatted);

		// en_GB
		$formatted = DateTime::formatDateTime($dateTime, 'en_GB', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
		$this->assertEqualsIgnoringCase('15/01/2024, 14:30', $formatted);

		// en_US
		$formatted = DateTime::formatDateTime($dateTime, 'en_US', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
		$this->assertEqualsIgnoringCase('1/15/24, 2:30 PM', $formatted);

		// Medium format

		// fr_FR
		$formatted = DateTime::formatDateTime($dateTime, 'fr_FR', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::MEDIUM);
		$this->assertEqualsIgnoringCase('15 janv. 2024, 14:30:45', $formatted);

		// en_GB
		$formatted = DateTime::formatDateTime($dateTime, 'en_GB', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::MEDIUM);
		$this->assertEqualsIgnoringCase('15 Jan 2024, 14:30:45', $formatted);

		// en_US
		$formatted = DateTime::formatDateTime($dateTime, 'en_US', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::MEDIUM);
		$this->assertEqualsIgnoringCase('Jan 15, 2024, 2:30:45 PM', $formatted);

		// Long format

		// fr_FR
		$formatted = DateTime::formatDateTime($dateTime, 'fr_FR', \IntlDateFormatter::LONG, \IntlDateFormatter::LONG);
		$this->assertEqualsIgnoringCase('15 janvier 2024 à 14:30:45 UTC', $formatted);

		// en_GB
		$formatted = DateTime::formatDateTime($dateTime, 'en_GB', \IntlDateFormatter::LONG, \IntlDateFormatter::LONG);
		$this->assertEqualsIgnoringCase('15 January 2024 at 14:30:45 UTC', $formatted);

		// en_US
		$formatted = DateTime::formatDateTime($dateTime, 'en_US', \IntlDateFormatter::LONG, \IntlDateFormatter::LONG);
		$this->assertEqualsIgnoringCase('January 15, 2024 at 2:30:45 PM UTC', $formatted);
		// Full format

		// fr_FR
		$formatted = DateTime::formatDateTime($dateTime, 'fr_FR', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL);
		$this->assertEqualsIgnoringCase('lundi 15 janvier 2024 à 14:30:45 temps universel coordonné', $formatted);

		// en_GB
		$formatted = DateTime::formatDateTime($dateTime, 'en_GB', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL);
		$this->assertEqualsIgnoringCase('Monday 15 January 2024 at 14:30:45 Coordinated Universal Time', $formatted);

		// en_US
		$formatted = DateTime::formatDateTime($dateTime, 'en_US', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL);
		$this->assertEqualsIgnoringCase('Monday, January 15, 2024 at 2:30:45 PM Coordinated Universal Time', $formatted);
	}

	// Date Formatting Methods

	public function testFormatDate(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');

		// fr_FR
		$this->assertEqualsIgnoringCase('15/01/2024', DateTime::formatDate($dateTime, 'fr_FR'));
		$this->assertEqualsIgnoringCase('15/01/2024', DateTime::formatDate($dateTime, 'en_GB'));
		$this->assertEqualsIgnoringCase('1/15/24', DateTime::formatDate($dateTime, 'en_US'));
	}

	public function testFormatDateShort(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');

		$this->assertEqualsIgnoringCase('15/01/2024', DateTime::formatDateShort($dateTime, 'fr_FR'));
		$this->assertEqualsIgnoringCase('15/01/2024', DateTime::formatDateShort($dateTime, 'en_GB'));
		$this->assertEqualsIgnoringCase('1/15/24', DateTime::formatDateShort($dateTime, 'en_US'));

		// fr_FR with dash separator
		$formatted = DateTime::formatDateShort($dateTime, 'fr_FR', '-');
		$this->assertEqualsIgnoringCase('15-01-2024', $formatted);

		// en_GB with dash separator
		$formatted = DateTime::formatDateShort($dateTime, 'en_GB', '-');
		$this->assertEqualsIgnoringCase('15-01-2024', $formatted);

		// en_US with dash separator
		$formatted = DateTime::formatDateShort($dateTime, 'en_US', '-');
		$this->assertEqualsIgnoringCase('1-15-24', $formatted);

		// fr_FR with dot separator
		$formatted = DateTime::formatDateShort($dateTime, 'fr_FR', '.');
		$this->assertEqualsIgnoringCase('15.01.2024', $formatted);

		// en_US with dot separator
		$formatted = DateTime::formatDateShort($dateTime, 'en_US', '.');
		$this->assertEqualsIgnoringCase('1.15.24', $formatted);

		// fr_FR with space separator
		$formatted = DateTime::formatDateShort($dateTime, 'fr_FR', ' ');
		$this->assertEqualsIgnoringCase('15 01 2024', $formatted);
	}

	public function testFormatDateMedium(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');

		$this->assertEqualsIgnoringCase('15 janv. 2024', DateTime::formatDateMedium($dateTime, 'fr_FR'));
		$this->assertEqualsIgnoringCase('15 Jan 2024', DateTime::formatDateMedium($dateTime, 'en_GB'));
		$this->assertEqualsIgnoringCase('Jan 15, 2024', DateTime::formatDateMedium($dateTime, 'en_US'));
	}

	public function testFormatDateLong(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');

		$this->assertEqualsIgnoringCase('15 janvier 2024', DateTime::formatDateLong($dateTime, 'fr_FR'));
		$this->assertEqualsIgnoringCase('15 January 2024', DateTime::formatDateLong($dateTime, 'en_GB'));
		$this->assertEqualsIgnoringCase('January 15, 2024', DateTime::formatDateLong($dateTime, 'en_US'));
	}

	public function testFormatDateFull(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');

		$this->assertEqualsIgnoringCase('lundi 15 janvier 2024', DateTime::formatDateFull($dateTime, 'fr_FR'));
		$this->assertEqualsIgnoringCase('Monday 15 January 2024', DateTime::formatDateFull($dateTime, 'en_GB'));
		$this->assertEqualsIgnoringCase('Monday, January 15, 2024', DateTime::formatDateFull($dateTime, 'en_US'));
	}

	public function testFormatDateISO(): void
	{
		$formatted = DateTime::formatDateISO(new \DateTime('2024-01-15 14:30:45'));
		$this->assertEquals('2024-01-15', $formatted);
	}

	/* ===================== Time Formatting Methods ===================== */

	public function testFormatTime(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');

		$this->assertEqualsIgnoringCase('14:30', DateTime::formatTime($dateTime, 'fr_FR'));
		$this->assertEqualsIgnoringCase('14:30', DateTime::formatTime($dateTime, 'en_GB'));
		$this->assertEqualsIgnoringCase('2:30 PM', DateTime::formatTime($dateTime, 'en_US'));

		$this->assertEqualsIgnoringCase('12:00 AM', DateTime::formatTime(new \DateTime('2024-01-15 00:00:00'), 'en_US'));
	}

	public function testFormatTimeShort(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');

		$this->assertEqualsIgnoringCase('14:30', DateTime::formatTimeShort($dateTime, 'fr_FR'));
		$this->assertEqualsIgnoringCase('14:30', DateTime::formatTimeShort($dateTime, 'en_GB'));
		$this->assertEqualsIgnoringCase('2:30 PM', DateTime::formatTimeShort($dateTime, 'en_US'));
	}

	public function testFormatTimeMedium(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');

		$this->assertEqualsIgnoringCase('14:30:45', DateTime::formatTimeMedium($dateTime, 'fr_FR'));
		$this->assertEqualsIgnoringCase('14:30:45', DateTime::formatTimeMedium($dateTime, 'en_GB'));
		$this->assertEqualsIgnoringCase('2:30:45 PM', DateTime::formatTimeMedium($dateTime, 'en_US'));
	}

	public function testFormatTimeLong(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');

		$this->assertEqualsIgnoringCase('14:30:45 UTC', DateTime::formatTimeLong($dateTime, 'fr_FR'));
		$this->assertEqualsIgnoringCase('14:30:45 UTC', DateTime::formatTimeLong($dateTime, 'en_GB'));
		$this->assertEqualsIgnoringCase('2:30:45 PM UTC', DateTime::formatTimeLong($dateTime, 'en_US'));
	}

	public function testFormatTimeISO(): void
	{
		$formatted = DateTime::formatTimeISO(new \DateTime('2024-01-15 14:30:45'));
		$this->assertEquals('14:30:45', $formatted);

		$formatted = DateTime::formatTimeISO(new \DateTime('2024-01-15 00:00:00'));
		$this->assertEquals('00:00:00', $formatted);

		$formatted = DateTime::formatTimeISO(new \DateTime('2024-01-15 23:59:59'));
		$this->assertEquals('23:59:59', $formatted);
	}

	/* ===================== Twig Formatting Methods ===================== */

	public function testFormatFromTwig(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');

		$formatted = DateTime::formatFromTwig($dateTime);
		$this->assertIsString($formatted);

		$formatted = DateTime::formatFromTwig($dateTime, 'long', 'medium');
		$this->assertIsString($formatted);

		// Avec string
		$formatted = DateTime::formatFromTwig('2024-01-15 14:30:45');
		$this->assertIsString($formatted);

		// Null
		$this->assertNull(DateTime::formatFromTwig(null));
	}

	public function testFormatDateFromTwig(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');
		$formatted = DateTime::formatDateFromTwig($dateTime);
		$this->assertIsString($formatted);

		$this->assertNull(DateTime::formatDateFromTwig(null));
	}

	public function testFormatTimeFromTwig(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');
		$formatted = DateTime::formatTimeFromTwig($dateTime);
		$this->assertIsString($formatted);

		$this->assertNull(DateTime::formatTimeFromTwig(null));
	}

	/* ===================== UTC & Timezone Methods ===================== */

	public function testGetUTCSqlDate(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45', new \DateTimeZone('Europe/Paris'));
		$utcDate = DateTime::getUTCSqlDate($dateTime);
		$this->assertIsString($utcDate);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $utcDate);

		$this->assertNull(DateTime::getUTCSqlDate(null));
	}

	public function testGetUTCSqlTime(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45', new \DateTimeZone('Europe/Paris'));
		$utcTime = DateTime::getUTCSqlTime($dateTime);
		$this->assertIsString($utcTime);
		$this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $utcTime);

		// Epoch date (1970-01-01): must be normalized to today before DST conversion
		$epochDateTime = new \DateTime('1970-01-01 14:30:00', new \DateTimeZone('Europe/Paris'));
		$utcEpochTime = DateTime::getUTCSqlTime($epochDateTime);
		$this->assertIsString($utcEpochTime);
		$this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $utcEpochTime);
		// Result must differ from naive conversion (which would use 1970 DST rules)
		$naiveUtcTime = (clone $epochDateTime)->setTimezone(new \DateTimeZone('UTC'))->format('H:i:s');
		$normalizedDateTime = (clone $epochDateTime)->setDate((int) date('Y'), (int) date('m'), (int) date('d'));
		$expectedUtcTime = (clone $normalizedDateTime)->setTimezone(new \DateTimeZone('UTC'))->format('H:i:s');
		$this->assertSame($expectedUtcTime, $utcEpochTime);

		$this->assertNull(DateTime::getUTCSqlTime(null));
	}

	public function testGetUTCSqlDateTime(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45', new \DateTimeZone('Europe/Paris'));
		$utcDateTime = DateTime::getUTCSqlDateTime($dateTime);
		$this->assertIsString($utcDateTime);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $utcDateTime);

		$this->assertNull(DateTime::getUTCSqlDateTime(null));
	}

	public function testConvertToTimezone(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45', new \DateTimeZone('UTC'));
		$result = DateTime::convertToTimezone($dateTime, 'Europe/Paris');
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('Europe/Paris', $result->getTimezone()->getName());

		// Original unchanged
		$this->assertEquals('UTC', $dateTime->getTimezone()->getName());
	}

	public function testConvertToUTC(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45', new \DateTimeZone('Europe/Paris'));
		$result = DateTime::convertToUTC($dateTime);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('UTC', $result->getTimezone()->getName());
	}

	public function testGetTimezoneName(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45', new \DateTimeZone('Europe/Paris'));
		$this->assertEquals('Europe/Paris', DateTime::getTimezoneName($dateTime));
	}

	public function testGetTimezoneOffset(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45', new \DateTimeZone('Europe/Paris'));
		$offset = DateTime::getTimezoneOffset($dateTime);
		$this->assertIsInt($offset);
	}

	/* ===================== Date Manipulation Methods ===================== */

	public function testAddDays(): void
	{
		$dateTime = new \DateTime('2024-01-15');
		$result = DateTime::addDays($dateTime, 5);
		$this->assertEquals('2024-01-20', $result->format('Y-m-d'));
	}

	public function testSubDays(): void
	{
		$dateTime = new \DateTime('2024-01-15');
		$result = DateTime::subDays($dateTime, 5);
		$this->assertEquals('2024-01-10', $result->format('Y-m-d'));
	}

	public function testMoveBackOfNbDays(): void
	{
		$date = new \DateTime('2024-01-15');
		$result = DateTime::moveBackOfNbDays($date, 5);
		$this->assertEquals('2024-01-10', $result->format('Y-m-d'));

		// Vérifier que l'original n'est pas modifié
		$this->assertEquals('2024-01-15', $date->format('Y-m-d'));
	}

	public function testMoveForwardOfNbDays(): void
	{
		$date = new \DateTime('2024-01-15');
		$result = DateTime::moveForwardOfNbDays($date, 5);
		$this->assertEquals('2024-01-20', $result->format('Y-m-d'));

		// Vérifier que l'original n'est pas modifié
		$this->assertEquals('2024-01-15', $date->format('Y-m-d'));
	}

	public function testAddMonths(): void
	{
		$dateTime = new \DateTime('2024-01-15');
		$result = DateTime::addMonths($dateTime, 2);
		$this->assertEquals('2024-03-15', $result->format('Y-m-d'));
	}

	public function testSubMonths(): void
	{
		$dateTime = new \DateTime('2024-03-15');
		$result = DateTime::subMonths($dateTime, 2);
		$this->assertEquals('2024-01-15', $result->format('Y-m-d'));
	}

	public function testMoveBackOfNbMonths(): void
	{
		$date = new \DateTime('2024-03-15');
		$result = DateTime::moveBackOfNbMonths($date, 2);
		$this->assertEquals('2024-01-15', $result->format('Y-m-d'));
		$this->assertEquals('2024-03-15', $date->format('Y-m-d')); // Original unchanged
	}

	public function testMoveForwardOfNbMonths(): void
	{
		$date = new \DateTime('2024-01-15');
		$result = DateTime::moveForwardOfNbMonths($date, 2);
		$this->assertEquals('2024-03-15', $result->format('Y-m-d'));
		$this->assertEquals('2024-01-15', $date->format('Y-m-d')); // Original unchanged
	}

	public function testAddYears(): void
	{
		$dateTime = new \DateTime('2024-01-15');
		$result = DateTime::addYears($dateTime, 2);
		$this->assertEquals('2026-01-15', $result->format('Y-m-d'));
	}

	public function testSubYears(): void
	{
		$dateTime = new \DateTime('2024-01-15');
		$result = DateTime::subYears($dateTime, 2);
		$this->assertEquals('2022-01-15', $result->format('Y-m-d'));
	}

	/* ===================== Time Manipulation Methods ===================== */

	public function testAddHours(): void
	{
		$dateTime = new \DateTime('2024-01-15 10:00:00');
		$result = DateTime::addHours($dateTime, 3);
		$this->assertEquals('13:00:00', $result->format('H:i:s'));

		// Original unchanged
		$this->assertEquals('10:00:00', $dateTime->format('H:i:s'));
	}

	public function testSubHours(): void
	{
		$dateTime = new \DateTime('2024-01-15 15:00:00');
		$result = DateTime::subHours($dateTime, 3);
		$this->assertEquals('12:00:00', $result->format('H:i:s'));
	}

	public function testAddMinutes(): void
	{
		$dateTime = new \DateTime('2024-01-15 10:00:00');
		$result = DateTime::addMinutes($dateTime, 45);
		$this->assertEquals('10:45:00', $result->format('H:i:s'));
	}

	public function testSubMinutes(): void
	{
		$dateTime = new \DateTime('2024-01-15 10:45:00');
		$result = DateTime::subMinutes($dateTime, 30);
		$this->assertEquals('10:15:00', $result->format('H:i:s'));
	}

	public function testAddSeconds(): void
	{
		$dateTime = new \DateTime('2024-01-15 10:00:00');
		$result = DateTime::addSeconds($dateTime, 90);
		$this->assertEquals('10:01:30', $result->format('H:i:s'));
	}

	public function testSubSeconds(): void
	{
		$dateTime = new \DateTime('2024-01-15 10:01:30');
		$result = DateTime::subSeconds($dateTime, 45);
		$this->assertEquals('10:00:45', $result->format('H:i:s'));
	}

	/* ===================== Time Rounding Methods ===================== */

	public function testFloorToHour(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:45:30');
		$result = DateTime::floorToHour($dateTime);
		$this->assertEquals('14:00:00', $result->format('H:i:s'));
	}

	public function testCeilToHour(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:01:00');
		$result = DateTime::ceilToHour($dateTime);
		$this->assertEquals('15:00:00', $result->format('H:i:s'));

		// Already on the hour
		$dateTime = new \DateTime('2024-01-15 14:00:00');
		$result = DateTime::ceilToHour($dateTime);
		$this->assertEquals('14:00:00', $result->format('H:i:s'));
	}

	public function testRoundToHour(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:25:00');
		$result = DateTime::roundToHour($dateTime);
		$this->assertEquals('14:00:00', $result->format('H:i:s'));

		$dateTime = new \DateTime('2024-01-15 14:35:00');
		$result = DateTime::roundToHour($dateTime);
		$this->assertEquals('15:00:00', $result->format('H:i:s'));
	}

	public function testFloorToMinutes(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:37:30');
		$result = DateTime::floorToMinutes($dateTime, 15);
		$this->assertEquals('14:30:00', $result->format('H:i:s'));
	}

	public function testCeilToMinutes(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:31:00');
		$result = DateTime::ceilToMinutes($dateTime, 15);
		$this->assertEquals('14:45:00', $result->format('H:i:s'));
	}

	/* ===================== DateTime Comparison Methods ===================== */

	public function testIsInThePast(): void
	{
		$past = new \DateTime('-1 day');
		$this->assertTrue(DateTime::isInThePast($past));

		$future = new \DateTime('+1 day');
		$this->assertFalse(DateTime::isInThePast($future));
	}

	public function testIsInTheFuture(): void
	{
		$future = new \DateTime('+1 day');
		$this->assertTrue(DateTime::isInTheFuture($future));

		$past = new \DateTime('-1 day');
		$this->assertFalse(DateTime::isInTheFuture($past));
	}

	public function testIsSameDateTime(): void
	{
		$dateTime1 = new \DateTime('2024-01-15 14:30:45');
		$dateTime2 = new \DateTime('2024-01-15 14:30:45');
		$this->assertTrue(DateTime::isSameDateTime($dateTime1, $dateTime2));

		$dateTime3 = new \DateTime('2024-01-15 14:30:46');
		$this->assertFalse(DateTime::isSameDateTime($dateTime1, $dateTime3));
	}

	public function testIsBetweenDateTimes(): void
	{
		$dateTime = new \DateTime('2024-01-15 12:00:00');
		$start = new \DateTime('2024-01-15 10:00:00');
		$end = new \DateTime('2024-01-15 14:00:00');
		$this->assertTrue(DateTime::isBetweenDateTimes($dateTime, $start, $end));

		$dateTime2 = new \DateTime('2024-01-15 16:00:00');
		$this->assertFalse(DateTime::isBetweenDateTimes($dateTime2, $start, $end));

		// Test boundaries
		$this->assertTrue(DateTime::isBetweenDateTimes($start, $start, $end, true));
		$this->assertFalse(DateTime::isBetweenDateTimes($start, $start, $end, false));
	}

	/* ===================== Date Comparison Methods ===================== */

	public function testIsDateAfter(): void
	{
		$date1 = new \DateTime('2024-01-20');
		$date2 = new \DateTime('2024-01-15');
		$this->assertTrue(DateTime::isDateAfter($date1, $date2));
		$this->assertFalse(DateTime::isDateAfter($date2, $date1));

		// Même date
		$date3 = new \DateTime('2024-01-15');
		$this->assertFalse(DateTime::isDateAfter($date2, $date3));
	}

	public function testIsDateBefore(): void
	{
		$date1 = new \DateTime('2024-01-15');
		$date2 = new \DateTime('2024-01-20');
		$this->assertTrue(DateTime::isDateBefore($date1, $date2));
		$this->assertFalse(DateTime::isDateBefore($date2, $date1));

		// Même date
		$date3 = new \DateTime('2024-01-15');
		$this->assertFalse(DateTime::isDateBefore($date1, $date3));
	}

	public function testIsDateInThePast(): void
	{
		$yesterday = new \DateTime('yesterday');
		$this->assertTrue(DateTime::isDateInThePast($yesterday));

		$tomorrow = new \DateTime('tomorrow');
		$this->assertFalse(DateTime::isDateInThePast($tomorrow));
	}

	public function testIsDateInTheFuture(): void
	{
		$tomorrow = new \DateTime('tomorrow');
		$this->assertTrue(DateTime::isDateInTheFuture($tomorrow));

		$yesterday = new \DateTime('yesterday');
		$this->assertFalse(DateTime::isDateInTheFuture($yesterday));
	}

	public function testIsSameDay(): void
	{
		$date1 = new \DateTime('2024-01-15 10:00:00');
		$date2 = new \DateTime('2024-01-15 18:00:00');
		$this->assertTrue(DateTime::isSameDay($date1, $date2));

		$date3 = new \DateTime('2024-01-16');
		$this->assertFalse(DateTime::isSameDay($date1, $date3));
	}

	public function testIsToday(): void
	{
		$today = new \DateTime();
		$this->assertTrue(DateTime::isToday($today));

		$yesterday = new \DateTime('yesterday');
		$this->assertFalse(DateTime::isToday($yesterday));
	}

	// ========== Time Comparison Methods ==========

	public function testIsSameTime(): void
	{
		$dateTime1 = new \DateTime('2024-01-15 14:30:45');
		$dateTime2 = new \DateTime('2024-01-20 14:30:45'); // Different date
		$this->assertTrue(DateTime::isSameTime($dateTime1, $dateTime2));

		$dateTime3 = new \DateTime('2024-01-15 14:30:46');
		$this->assertFalse(DateTime::isSameTime($dateTime1, $dateTime3));
	}

	public function testIsSameHour(): void
	{
		$dateTime1 = new \DateTime('2024-01-15 14:30:45');
		$dateTime2 = new \DateTime('2024-01-15 14:45:00'); // Same hour
		$this->assertTrue(DateTime::isSameHour($dateTime1, $dateTime2));

		$dateTime3 = new \DateTime('2024-01-15 15:30:45');
		$this->assertFalse(DateTime::isSameHour($dateTime1, $dateTime3));
	}

	/* ===================== Day of Week Methods ===================== */

	public function testIsWeekend(): void
	{
		// Samedi
		$saturday = new \DateTime('2024-01-20'); // Samedi
		$this->assertTrue(DateTime::isWeekend($saturday));

		// Dimanche
		$sunday = new \DateTime('2024-01-21'); // Dimanche
		$this->assertTrue(DateTime::isWeekend($sunday));

		// Lundi
		$monday = new \DateTime('2024-01-15'); // Lundi
		$this->assertFalse(DateTime::isWeekend($monday));

		// Vendredi
		$friday = new \DateTime('2024-01-19'); // Vendredi
		$this->assertFalse(DateTime::isWeekend($friday));
	}

	public function testIsWeekday(): void
	{
		$monday = new \DateTime('2024-01-15');
		$this->assertTrue(DateTime::isWeekday($monday));

		$saturday = new \DateTime('2024-01-20');
		$this->assertFalse(DateTime::isWeekday($saturday));
	}

	public function testGetDayOfWeek(): void
	{
		$monday = new \DateTime('2024-01-15');
		$this->assertEquals(1, DateTime::getDayOfWeek($monday));

		$sunday = new \DateTime('2024-01-21');
		$this->assertEquals(7, DateTime::getDayOfWeek($sunday));
	}

	public function testGetNextWeekDay(): void
	{
		$monday = new \DateTime('2024-01-15'); // Monday
		$result = DateTime::getNextWeekDay($monday, 5); // Find Friday
		$this->assertEquals('5', $result->format('N'));
		$this->assertEquals('2024-01-19', $result->format('Y-m-d'));
	}

	public function testGetPreviousWeekDay(): void
	{
		$friday = new \DateTime('2024-01-19'); // Friday
		$result = DateTime::getPreviousWeekDay($friday, 1); // Find Monday
		$this->assertEquals('1', $result->format('N'));
		$this->assertEquals('2024-01-15', $result->format('Y-m-d'));
	}

	/* ===================== Week Methods ===================== */

	public function testGetWeekNumber(): void
	{
		$date = new \DateTime('2024-01-15');
		[$year, $weekNumber] = DateTime::getWeekNumber($date);

		$this->assertIsString($year);
		$this->assertIsString($weekNumber);
		$this->assertEquals('2024', $year);
		$this->assertMatchesRegularExpression('/^\d{2}$/', $weekNumber);

		// Test pour une date en fin d'année où la semaine appartient à l'année suivante
		$date = new \DateTime('2024-12-30'); // Semaine 1 de 2025
		[$year, $weekNumber] = DateTime::getWeekNumber($date);

		if ((int)$weekNumber === 1) {
			$this->assertEquals('2025', $year);
		}
	}

	public function testGetFirstDayOfCurrentWeek(): void
	{
		$result = DateTime::getFirstDayOfCurrentWeek();
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('1', $result->format('N')); // 1 = Lundi
	}

	public function testGetLastDayOfCurrentWeek(): void
	{
		$result = DateTime::getLastDayOfCurrentWeek();
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('7', $result->format('N')); // 7 = Dimanche
	}

	public function testGetFirstDayOfPreviousWeek(): void
	{
		$result = DateTime::getFirstDayOfPreviousWeek();
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('1', $result->format('N')); // 1 = Lundi
	}

	public function testGetLastDayOfPreviousWeek(): void
	{
		$result = DateTime::getLastDayOfPreviousWeek();
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('7', $result->format('N')); // 7 = Dimanche
	}

	public function testGetFirstDayOfNextWeek(): void
	{
		$result = DateTime::getFirstDayOfNextWeek();
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('1', $result->format('N'));
	}

	public function testGetLastDayOfNextWeek(): void
	{
		$result = DateTime::getLastDayOfNextWeek();
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('7', $result->format('N'));
	}

	public function testGetFirstDayOfWeek(): void
	{
		$result = DateTime::getFirstDayOfWeek(2024, 3);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('1', $result->format('N'));
	}

	public function testGetLastDayOfWeek(): void
	{
		$result = DateTime::getLastDayOfWeek(2024, 3);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('7', $result->format('N'));
	}

	public function testGetFirstDayOfWeekOfDate(): void
	{
		$date = new \DateTime('2024-01-15');
		$result = DateTime::getFirstDayOfWeekOfDate($date);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('1', $result->format('N'));
	}

	public function testGetLastDayOfWeekOfDate(): void
	{
		$date = new \DateTime('2024-01-15');
		$result = DateTime::getLastDayOfWeekOfDate($date);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('7', $result->format('N'));
	}

	/* ===================== Month Methods ===================== */

	public function testGetFirstDayOfCurrentMonth(): void
	{
		$result = DateTime::getFirstDayOfCurrentMonth();
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('01', $result->format('d'));
	}

	public function testGetLastDayOfCurrentMonth(): void
	{
		$result = DateTime::getLastDayOfCurrentMonth();
		$this->assertInstanceOf(\DateTime::class, $result);
		$lastDay = (int)$result->format('d');
		$this->assertGreaterThanOrEqual(28, $lastDay);
		$this->assertLessThanOrEqual(31, $lastDay);
	}

	public function testGetFirstDayOfPreviousMonth(): void
	{
		$result = DateTime::getFirstDayOfPreviousMonth();
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('01', $result->format('d'));
	}

	public function testGetLastDayOfPreviousMonth(): void
	{
		$result = DateTime::getLastDayOfPreviousMonth();
		$this->assertInstanceOf(\DateTime::class, $result);
		$lastDay = (int)$result->format('d');
		$this->assertGreaterThanOrEqual(28, $lastDay);
		$this->assertLessThanOrEqual(31, $lastDay);
	}

	public function testGetFirstDayOfNextMonth(): void
	{
		$result = DateTime::getFirstDayOfNextMonth();
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('01', $result->format('d'));
	}

	public function testGetLastDayOfNextMonth(): void
	{
		$result = DateTime::getLastDayOfNextMonth();
		$this->assertInstanceOf(\DateTime::class, $result);
		$lastDay = (int)$result->format('d');
		$this->assertGreaterThanOrEqual(28, $lastDay);
		$this->assertLessThanOrEqual(31, $lastDay);
	}

	public function testGetFirstDayOfMonth(): void
	{
		$result = DateTime::getFirstDayOfMonth(2024, 3);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('2024-03-01', $result->format('Y-m-d'));
	}

	public function testGetLastDayOfMonth(): void
	{
		$result = DateTime::getLastDayOfMonth(2024, 2);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('2024-02-29', $result->format('Y-m-d')); // 2024 is leap year
	}

	public function testGetFirstDayOfMonthOfDate(): void
	{
		$date = new \DateTime('2024-03-15');
		$result = DateTime::getFirstDayOfMonthOfDate($date);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('2024-03-01', $result->format('Y-m-d'));
	}

	public function testGetLastDayOfMonthOfDate(): void
	{
		$date = new \DateTime('2024-03-15');
		$result = DateTime::getLastDayOfMonthOfDate($date);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('2024-03-31', $result->format('Y-m-d'));
	}

	public function testGetWeekDayOfMonth(): void
	{
		// 2nd Wednesday of January 2024
		$result = DateTime::getWeekDayOfMonth(2024, 1, 3, 2);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('3', $result->format('N')); // Wednesday
		$this->assertEquals('2024-01', $result->format('Y-m'));

		// Invalid weekday
		$result = DateTime::getWeekDayOfMonth(2024, 1, 8, 1);
		$this->assertNull($result);

		// Invalid number
		$result = DateTime::getWeekDayOfMonth(2024, 1, 1, 6);
		$this->assertNull($result);

		// Non-existent (5th Monday of February 2024)
		$result = DateTime::getWeekDayOfMonth(2024, 2, 1, 5);
		$this->assertNull($result);
	}

	public function testGetLastWeekDayOfMonth(): void
	{
		// Last Friday of January 2024
		$result = DateTime::getLastWeekDayOfMonth(2024, 1, 5);
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('5', $result->format('N')); // Friday
		$this->assertEquals('2024-01', $result->format('Y-m'));

		// Invalid
		$result = DateTime::getLastWeekDayOfMonth(2024, 1, 8);
		$this->assertNull($result);
	}

	/* ===================== Year Methods ===================== */

	public function testCalculateAge(): void
	{
		$birthDate = new \DateTime('-30 years');
		$age = DateTime::calculateAge($birthDate);
		$this->assertEquals(30, $age);

		$birthDate = new \DateTime('-25 years -6 months');
		$age = DateTime::calculateAge($birthDate);
		$this->assertEquals(25, $age);
	}

	public function testGetAge(): void
	{
		$birthDate = new \DateTime('-30 years');
		$age = DateTime::getAge($birthDate);
		$this->assertEquals(30, $age);
	}

	/* ===================== Time Calculation Methods ===================== */

	public function testGetHoursBetween(): void
	{
		$start = new \DateTime('2024-01-15 10:00:00');
		$end = new \DateTime('2024-01-15 13:30:00');
		$hours = DateTime::getHoursBetween($start, $end);
		$this->assertEquals(3.5, $hours);

		// Test absolute
		$this->assertEquals(3.5, DateTime::getHoursBetween($end, $start));

		// Test non-absolute
		$this->assertEquals(-3.5, DateTime::getHoursBetween($end, $start, false));
	}

	public function testGetMinutesBetween(): void
	{
		$start = new \DateTime('2024-01-15 10:00:00');
		$end = new \DateTime('2024-01-15 10:45:00');
		$minutes = DateTime::getMinutesBetween($start, $end);
		$this->assertEquals(45, $minutes);
	}

	public function testGetSecondsBetween(): void
	{
		$start = new \DateTime('2024-01-15 10:00:00');
		$end = new \DateTime('2024-01-15 10:01:30');
		$seconds = DateTime::getSecondsBetween($start, $end);
		$this->assertEquals(90, $seconds);
	}

	public function testGetSecondsSinceMidnight(): void
	{
		$day = new \DateTime('2024-01-15 06:00:00');
		$dateTime = new \DateTime('2024-01-15 10:01:30');
		$seconds = DateTime::getSecondsSinceMidnight($dateTime, $day);
		$this->assertEquals(10 * 3600 + 90, $seconds);

		// Test with a datetime on the day following the reference day (range crossing midnight)
		$nextDay = new \DateTime('2024-01-16 01:00:00');
		$this->assertEquals(25 * 3600, DateTime::getSecondsSinceMidnight($nextDay, $day));
	}

	public function testGetTimestamp(): void
	{
		$dateTime = new \DateTime('2024-01-15 10:00:00');
		$timestamp = DateTime::getTimestamp($dateTime);
		$this->assertIsInt($timestamp);
		$this->assertGreaterThan(0, $timestamp);
	}

	public function testGetMilliseconds(): void
	{
		$dateTime = new \DateTime('2024-01-15 10:00:00');
		$milliseconds = DateTime::getMilliseconds($dateTime);
		$this->assertIsInt($milliseconds);
		$this->assertGreaterThan(0, $milliseconds);
		$this->assertEquals(DateTime::getTimestamp($dateTime) * 1000, $milliseconds);
	}

	/* ===================== Working Days & Business Days Methods ===================== */

	public function testIsWorkingDay(): void
	{
		// Monday - working day
		$monday = new \DateTime('2024-01-15');
		$this->assertTrue(DateTime::isWorkingDay($monday, false));

		// Saturday - not working day
		$saturday = new \DateTime('2024-01-20');
		$this->assertFalse(DateTime::isWorkingDay($saturday, false));

		// Sunday - not working day
		$sunday = new \DateTime('2024-01-21');
		$this->assertFalse(DateTime::isWorkingDay($sunday, false));
	}

	public function testIsBusinessDay(): void
	{
		// Monday - business day
		$monday = new \DateTime('2024-01-15');
		$this->assertTrue(DateTime::isBusinessDay($monday, false));

		// Saturday - business day (6th day)
		$saturday = new \DateTime('2024-01-20');
		$this->assertTrue(DateTime::isBusinessDay($saturday, false));

		// Sunday - not business day
		$sunday = new \DateTime('2024-01-21');
		$this->assertFalse(DateTime::isBusinessDay($sunday, false));
	}

	public function testGetBusinessDays(): void
	{
		// Monday to Friday (same week)
		$monday = new \DateTime('2024-01-15'); // Monday
		$friday = new \DateTime('2024-01-19'); // Friday
		$businessDays = DateTime::getBusinessDays($monday, $friday);
		$this->assertEquals(5, $businessDays);

		// Friday to next Monday (over weekend)
		$friday = new \DateTime('2024-01-19'); // Friday
		$nextMonday = new \DateTime('2024-01-22'); // Monday
		$businessDays = DateTime::getBusinessDays($friday, $nextMonday);
		$this->assertEquals(2, $businessDays); // Friday and Monday

		// Same day
		$date = new \DateTime('2024-01-15'); // Monday
		$businessDays = DateTime::getBusinessDays($date, $date);
		$this->assertEquals(1, $businessDays);

		// Reversed dates (should swap automatically)
		$businessDays = DateTime::getBusinessDays($friday, $monday);
		$this->assertEquals(5, $businessDays);
	}

	public function testAddBusinessDays(): void
	{
		// Add 5 business days from Monday (should land on next Monday)
		$monday = new \DateTime('2024-01-15'); // Monday
		$result = DateTime::addBusinessDays($monday, 5);
		$this->assertEquals('2024-01-22', $result->format('Y-m-d')); // Next Monday

		// Add business days from Friday (should skip weekend)
		$friday = new \DateTime('2024-01-19'); // Friday
		$result = DateTime::addBusinessDays($friday, 1);
		$this->assertEquals('2024-01-22', $result->format('Y-m-d')); // Monday

		// Original unchanged
		$this->assertEquals('2024-01-19', $friday->format('Y-m-d'));
	}

	public function testSubBusinessDays(): void
	{
		// Subtract 5 business days from Friday (should land on previous Friday)
		$friday = new \DateTime('2024-01-19'); // Friday
		$result = DateTime::subBusinessDays($friday, 5);
		$this->assertEquals('2024-01-12', $result->format('Y-m-d')); // Previous Friday

		// Subtract business days from Monday (should skip weekend)
		$monday = new \DateTime('2024-01-22'); // Monday
		$result = DateTime::subBusinessDays($monday, 1);
		$this->assertEquals('2024-01-19', $result->format('Y-m-d')); // Previous Friday

		// Original unchanged
		$this->assertEquals('2024-01-22', $monday->format('Y-m-d'));
	}

	public function testStartOfDay(): void
	{
		$dateTime = new \DateTime('2024-01-15 14:30:45');
		$result = DateTime::startOfDay($dateTime);
		$this->assertEquals('2024-01-15 00:00:00', $result->format('Y-m-d H:i:s'));

		// Original unchanged
		$this->assertEquals('2024-01-15 14:30:45', $dateTime->format('Y-m-d H:i:s'));
	}

	/* ===================== Validation Methods ===================== */

	public function testIsValidRange(): void
	{
		$minDate = new \DateTime('2024-01-01');
		$maxDate = new \DateTime('2024-12-31');

		// Valid - within range
		$validDate = new \DateTime('2024-06-15');
		$this->assertTrue(DateTime::isValidRange($validDate, $minDate, $maxDate));

		// Valid - on boundaries
		$this->assertTrue(DateTime::isValidRange($minDate, $minDate, $maxDate));
		$this->assertTrue(DateTime::isValidRange($maxDate, $minDate, $maxDate));

		// Invalid - before range
		$beforeDate = new \DateTime('2023-12-31');
		$this->assertFalse(DateTime::isValidRange($beforeDate, $minDate, $maxDate));

		// Invalid - after range
		$afterDate = new \DateTime('2025-01-01');
		$this->assertFalse(DateTime::isValidRange($afterDate, $minDate, $maxDate));
	}

	/* ===================== Deprecated Methods ===================== */

	public function testParseDate(): void
	{
		$result = DateTime::parseDate('2024-01-15');
		$this->assertInstanceOf(\DateTime::class, $result);
		$this->assertEquals('2024-01-15', $result->format('Y-m-d'));

		// ISO format
		$result = DateTime::parseDate('2024-01-15T14:30:45');
		$this->assertInstanceOf(\DateTime::class, $result);

		// Empty
		$result = DateTime::parseDate('');
		$this->assertNull($result);
	}
}