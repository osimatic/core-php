<?php

namespace Tests\Calendar;

use Osimatic\Calendar\DatePeriod;
use Osimatic\Calendar\PeriodType;
use PHPUnit\Framework\TestCase;

final class DatePeriodTest extends TestCase
{
	// ========== Counting Methods ==========

	public function testGetNbDays(): void
	{
		// Same day
		$start = new \DateTime('2024-01-15 10:00:00');
		$end = new \DateTime('2024-01-15 14:00:00');
		$this->assertEquals(0, DatePeriod::getNbDays($start, $end));

		// With times included
		$this->assertLessThan(1, DatePeriod::getNbDays($start, $end, true));

		// 5 days
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-20');
		$this->assertEquals(5, DatePeriod::getNbDays($start, $end));

		// Negative period (end before start)
		$start = new \DateTime('2024-01-20');
		$end = new \DateTime('2024-01-15');
		$this->assertEquals(-5, DatePeriod::getNbDays($start, $end));

		// Same DateTime
		$date = new \DateTime('2024-01-15 12:00:00');
		$this->assertEquals(0, DatePeriod::getNbDays($date, $date));
		$this->assertEquals(0, DatePeriod::getNbDays($date, $date, true));

		// Large period (several years)
		$start = new \DateTime('2020-01-01');
		$end = new \DateTime('2024-12-31');
		$nbDays = DatePeriod::getNbDays($start, $end);
		$this->assertGreaterThan(1000, $nbDays);
		$this->assertEquals(1826, $nbDays); // 5 years with one leap year
	}

	public function testGetNbRemainingDays(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-20');
		$this->assertEquals(5, DatePeriod::getNbRemainingDays($start, $end));

		// Same day
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-15');
		$this->assertEquals(0, DatePeriod::getNbRemainingDays($start, $end));

		// Same DateTime
		$date = new \DateTime('2024-01-15 12:00:00');
		$this->assertEquals(0, DatePeriod::getNbRemainingDays($date, $date));

		// Full month
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-02-01');
		$remaining = DatePeriod::getNbRemainingDays($start, $end);
		$this->assertEquals(0, $remaining); // 1 month and 0 days remaining
	}

	public function testGetNbFullWeeks(): void
	{
		// 14 days = 2 full weeks
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-29');
		$this->assertEquals(2, DatePeriod::getNbFullWeeks($start, $end));

		// 10 days = 1 full week
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-25');
		$this->assertEquals(1, DatePeriod::getNbFullWeeks($start, $end));

		// 6 days = 0 full weeks
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-21');
		$this->assertEquals(0, DatePeriod::getNbFullWeeks($start, $end));

		// Zero days
		$date = new \DateTime('2024-01-15');
		$this->assertEquals(0, DatePeriod::getNbFullWeeks($date, $date));
	}

	public function testGetNbWeeks(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-29');
		$nbWeeks = DatePeriod::getNbWeeks($start, $end);
		$this->assertGreaterThan(0, $nbWeeks);

		// Same date
		$date = new \DateTime('2024-01-15');
		$nbWeeks = DatePeriod::getNbWeeks($date, $date);
		$this->assertGreaterThanOrEqual(0, $nbWeeks);
	}

	public function testGetNbFullMonths(): void
	{
		// 2 full months
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-03-15');
		$this->assertEquals(2, DatePeriod::getNbFullMonths($start, $end));

		// Same month
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-20');
		$this->assertEquals(0, DatePeriod::getNbFullMonths($start, $end));

		// 12 months = 1 year
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2025-01-01');
		$this->assertEquals(12, DatePeriod::getNbFullMonths($start, $end));

		// Same date
		$date = new \DateTime('2024-01-15');
		$this->assertEquals(0, DatePeriod::getNbFullMonths($date, $date));
	}

	public function testGetNbMonths(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-03-15');
		$nbMonths = DatePeriod::getNbMonths($start, $end);
		$this->assertGreaterThan(0, $nbMonths);

		// Same date
		$date = new \DateTime('2024-01-15');
		$nbMonths = DatePeriod::getNbMonths($date, $date);
		$this->assertGreaterThanOrEqual(0, $nbMonths);
	}

	public function testGetNbYears(): void
	{
		// 1 year
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2025-01-01');
		$this->assertEquals(1, DatePeriod::getNbYears($start, $end));

		// 2 years
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2026-01-01');
		$this->assertEquals(2, DatePeriod::getNbYears($start, $end));

		// 3 years
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2027-01-01');
		$this->assertEquals(3, DatePeriod::getNbYears($start, $end));

		// Less than one year
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-06-01');
		$this->assertEquals(0, DatePeriod::getNbYears($start, $end));

		// Zero days
		$date = new \DateTime('2024-01-15');
		$this->assertEquals(0, DatePeriod::getNbYears($date, $date));
	}

	// ========== List Generation Methods ==========

	public function testGetDays(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-17');

		$days = DatePeriod::getDays($start, $end);
		$this->assertCount(3, $days);
		$this->assertInstanceOf(\DateTime::class, $days[0]);
		$this->assertEquals('2024-01-15', $days[0]->format('Y-m-d'));
		$this->assertEquals('2024-01-16', $days[1]->format('Y-m-d'));
		$this->assertEquals('2024-01-17', $days[2]->format('Y-m-d'));

		// Single day
		$date = new \DateTime('2024-01-15');
		$days = DatePeriod::getDays($date, $date);
		$this->assertCount(1, $days);
		$this->assertEquals('2024-01-15', $days[0]->format('Y-m-d'));
	}

	public function testGetDaysWithWeekDaysFilter(): void
	{
		// Monday 15 to Friday 19 January 2024
		$start = new \DateTime('2024-01-15'); // Monday
		$end = new \DateTime('2024-01-19');   // Friday

		// Filter only Monday (1) and Friday (5)
		$days = DatePeriod::getDays($start, $end, [1, 5]);
		$this->assertCount(2, $days);
		$this->assertEquals('2024-01-15', $days[0]->format('Y-m-d')); // Monday
		$this->assertEquals('2024-01-19', $days[1]->format('Y-m-d')); // Friday

		// Empty filter (no days match)
		$days = DatePeriod::getDays($start, $end, []);
		$this->assertCount(0, $days);

		// Non-existent day in period (Sunday = 7)
		$days = DatePeriod::getDays($start, $end, [7]);
		$this->assertCount(0, $days);
	}

	public function testGetDaysFormatted(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-17');

		$days = DatePeriod::getDaysFormatted($start, $end);
		$this->assertEquals(['2024-01-15', '2024-01-16', '2024-01-17'], $days);

		// Custom format
		$days = DatePeriod::getDaysFormatted($start, $end, null, 'd/m/Y');
		$this->assertEquals(['15/01/2024', '16/01/2024', '17/01/2024'], $days);

		// Full format
		$days = DatePeriod::getDaysFormatted($start, $end, null, 'l, d F Y');
		$this->assertCount(3, $days);
		$this->assertIsString($days[0]);
	}

	public function testGetWeeks(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-02-15');

		$weeks = DatePeriod::getWeeks($start, $end);
		$this->assertIsArray($weeks);
		$this->assertGreaterThan(0, count($weeks));
		$this->assertInstanceOf(\DateTime::class, $weeks[0]);

		// Each element should be a Monday
		foreach ($weeks as $week) {
			$this->assertEquals(1, (int) $week->format('N')); // N=1 for Monday
		}

		// Same week
		$start = new \DateTime('2024-01-15'); // Monday
		$end = new \DateTime('2024-01-19');   // Friday (same week)
		$weeks = DatePeriod::getWeeks($start, $end);
		$this->assertIsArray($weeks);
		$this->assertGreaterThanOrEqual(1, count($weeks));

		// Across year boundary
		$start = new \DateTime('2024-12-15');
		$end = new \DateTime('2025-01-15');
		$weeks = DatePeriod::getWeeks($start, $end);
		$this->assertIsArray($weeks);
		$this->assertGreaterThan(0, count($weeks));
	}

	public function testGetWeeksFormatted(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-02-15');

		// Default ISO week format
		$weeks = DatePeriod::getWeeksFormatted($start, $end);
		$this->assertIsArray($weeks);
		$this->assertGreaterThan(0, count($weeks));
		foreach ($weeks as $week) {
			$this->assertMatchesRegularExpression('/\d{4}-\d{2}/', $week);
		}

		// Custom date format
		$weeks = DatePeriod::getWeeksFormatted($start, $end, 'Y-m-d');
		$this->assertIsArray($weeks);
		$this->assertGreaterThan(0, count($weeks));
		$this->assertIsString($weeks[0]);
		$this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $weeks[0]);
	}

	public function testGetMonths(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-04-15');

		$months = DatePeriod::getMonths($start, $end);
		$this->assertIsArray($months);
		$this->assertGreaterThan(0, count($months));
		$this->assertInstanceOf(\DateTime::class, $months[0]);

		// Each element should be the first day of a month
		foreach ($months as $month) {
			$this->assertEquals(1, (int) $month->format('d'));
		}

		// Same month
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-25');
		$months = DatePeriod::getMonths($start, $end);
		$this->assertIsArray($months);
		$this->assertGreaterThanOrEqual(1, count($months));
		$this->assertEquals('2024-01-01', $months[0]->format('Y-m-d'));

		// Across year boundary
		$start = new \DateTime('2024-11-15');
		$end = new \DateTime('2025-02-15');
		$months = DatePeriod::getMonths($start, $end);
		$this->assertIsArray($months);
		$this->assertGreaterThan(2, count($months));

		// Verify months cross year boundary
		$yearChanges = false;
		for ($i = 0; $i < count($months) - 1; $i++) {
			if ($months[$i]->format('Y') !== $months[$i + 1]->format('Y')) {
				$yearChanges = true;
				break;
			}
		}
		$this->assertTrue($yearChanges);
	}

	public function testGetMonthsFormatted(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-03-15');

		// Default format
		$months = DatePeriod::getMonthsFormatted($start, $end);
		$this->assertGreaterThan(0, count($months));
		$this->assertIsString($months[0]);

		// Custom year-month format
		$months = DatePeriod::getMonthsFormatted($start, $end, 'Y-m');
		$this->assertIsArray($months);
		$this->assertGreaterThan(0, count($months));
		$this->assertIsString($months[0]);
		$this->assertMatchesRegularExpression('/\d{4}-\d{2}/', $months[0]);

		// Month name format
		$months = DatePeriod::getMonthsFormatted($start, $end, 'F Y');
		$this->assertIsArray($months);
		$this->assertGreaterThan(0, count($months));
		$this->assertIsString($months[0]);
	}

	public function testCreateDatePeriodsFromTimeRanges(): void
	{
		$referenceDate = new \DateTime('2024-03-15');
		$timeRanges = [
			['09:00:00', '12:00:00'],
			['14:00:00', '18:00:00'],
		];

		$periods = DatePeriod::createDatePeriodsFromTimeRanges($timeRanges, $referenceDate);

		$this->assertIsArray($periods);
		$this->assertCount(2, $periods);

		// First period
		$this->assertInstanceOf(\DatePeriod::class, $periods[0]);
		$startDate = $periods[0]->getStartDate();
		$endDate = $periods[0]->getEndDate();
		$this->assertEquals('2024-03-15 09:00:00', $startDate->format('Y-m-d H:i:s'));
		$this->assertEquals('2024-03-15 12:00:00', $endDate->format('Y-m-d H:i:s'));

		// Second period
		$this->assertInstanceOf(\DatePeriod::class, $periods[1]);
		$startDate = $periods[1]->getStartDate();
		$endDate = $periods[1]->getEndDate();
		$this->assertEquals('2024-03-15 14:00:00', $startDate->format('Y-m-d H:i:s'));
		$this->assertEquals('2024-03-15 18:00:00', $endDate->format('Y-m-d H:i:s'));
	}

	public function testCreateDatePeriodsFromTimeRangesWithMidnightSpan(): void
	{
		$referenceDate = new \DateTime('2024-03-15');
		$timeRanges = [
			['22:00:00', '02:00:00'], // Night shift spanning midnight
		];

		$periods = DatePeriod::createDatePeriodsFromTimeRanges($timeRanges, $referenceDate);

		$this->assertCount(1, $periods);
		$this->assertInstanceOf(\DatePeriod::class, $periods[0]);

		$startDate = $periods[0]->getStartDate();
		$endDate = $periods[0]->getEndDate();

		// Start on the reference date
		$this->assertEquals('2024-03-15 22:00:00', $startDate->format('Y-m-d H:i:s'));

		// End on the next day
		$this->assertEquals('2024-03-16 02:00:00', $endDate->format('Y-m-d H:i:s'));
	}

	public function testCreateDatePeriodsFromTimeRangesWithMultiplePeriods(): void
	{
		$referenceDate = new \DateTime('2024-03-15');
		$timeRanges = [
			['08:00:00', '12:00:00'],
			['13:00:00', '17:00:00'],
			['18:00:00', '22:00:00'],
			['23:00:00', '01:00:00'], // Midnight span
		];

		$periods = DatePeriod::createDatePeriodsFromTimeRanges($timeRanges, $referenceDate);

		$this->assertCount(4, $periods);

		// Verify all are DatePeriod instances
		foreach ($periods as $period) {
			$this->assertInstanceOf(\DatePeriod::class, $period);
		}
	}

	public function testCreateDatePeriodsFromTimeRangesWithEmptyArray(): void
	{
		$referenceDate = new \DateTime('2024-03-15');
		$timeRanges = [];

		$periods = DatePeriod::createDatePeriodsFromTimeRanges($timeRanges, $referenceDate);

		$this->assertIsArray($periods);
		$this->assertCount(0, $periods);
	}

	public function testCreateDatePeriodsFromTimeRangesWithInvalidData(): void
	{
		$referenceDate = new \DateTime('2024-03-15');
		$timeRanges = [
			['09:00:00', '12:00:00'],         // Valid
			['invalid', 'data'],              // Invalid time format
			['14:00:00'],                     // Missing end time
			[],                               // Empty array
			'not-an-array',                   // Not an array
			['', '18:00:00'],                 // Empty start time
			['19:00:00', ''],                 // Empty end time
			[null, null],                     // Null values
			[123, 456],                       // Not strings
		];

		$periods = DatePeriod::createDatePeriodsFromTimeRanges($timeRanges, $referenceDate);

		// Should only return the one valid period
		$this->assertCount(1, $periods);
		$this->assertInstanceOf(\DatePeriod::class, $periods[0]);

		$startDate = $periods[0]->getStartDate();
		$this->assertEquals('2024-03-15 09:00:00', $startDate->format('Y-m-d H:i:s'));
	}

	public function testCreateDatePeriodsFromTimeRangesWithDifferentTimeFormats(): void
	{
		$referenceDate = new \DateTime('2024-03-15');
		$timeRanges = [
			['09:00:00', '12:00:00'],  // Full format with seconds
			['14:00', '18:00'],        // Without seconds
		];

		$periods = DatePeriod::createDatePeriodsFromTimeRanges($timeRanges, $referenceDate);

		$this->assertCount(2, $periods);

		// Both should work with PHP's flexible time parsing
		$this->assertInstanceOf(\DatePeriod::class, $periods[0]);
		$this->assertInstanceOf(\DatePeriod::class, $periods[1]);
	}

	public function testCreateDatePeriodsFromTimeRangesWithExactMidnight(): void
	{
		$referenceDate = new \DateTime('2024-03-15');
		$timeRanges = [
			['00:00:00', '23:59:59'], // Full day
		];

		$periods = DatePeriod::createDatePeriodsFromTimeRanges($timeRanges, $referenceDate);

		$this->assertCount(1, $periods);

		$startDate = $periods[0]->getStartDate();
		$endDate = $periods[0]->getEndDate();

		$this->assertEquals('2024-03-15 00:00:00', $startDate->format('Y-m-d H:i:s'));
		$this->assertEquals('2024-03-15 23:59:59', $endDate->format('Y-m-d H:i:s'));
	}

	public function testCreateDatePeriodsFromTimeRangesWithDifferentReferenceDates(): void
	{
		$timeRanges = [['09:00:00', '17:00:00']];

		// Test with different dates
		$dates = [
			new \DateTime('2024-01-01'),
			new \DateTime('2024-06-15'),
			new \DateTime('2024-12-31'),
		];

		foreach ($dates as $date) {
			$periods = DatePeriod::createDatePeriodsFromTimeRanges($timeRanges, $date);

			$this->assertCount(1, $periods);

			$startDate = $periods[0]->getStartDate();
			$this->assertEquals($date->format('Y-m-d') . ' 09:00:00', $startDate->format('Y-m-d H:i:s'));
		}
	}

	public function testCreateDatePeriodsFromTimeRangesDoesNotModifyReferenceDate(): void
	{
		$originalDate = new \DateTime('2024-03-15 10:30:45');
		$originalDateString = $originalDate->format('Y-m-d H:i:s');

		$timeRanges = [
			['22:00:00', '02:00:00'], // Causes date modification internally
		];

		DatePeriod::createDatePeriodsFromTimeRanges($timeRanges, $originalDate);

		// Original date should not be modified
		$this->assertEquals($originalDateString, $originalDate->format('Y-m-d H:i:s'));
	}

	// ========== Validation Methods ==========

	public function testIsFullWeek(): void
	{
		// Monday to Sunday
		$start = new \DateTime('2024-01-15 00:00:00'); // Monday
		$end = new \DateTime('2024-01-21 23:59:59');   // Sunday
		$this->assertTrue(DatePeriod::isFullWeek($start, $end));

		// Exactly 7 days from Monday 00:00:00 to Sunday 23:59:59
		$start = new \DateTime('2024-01-15 00:00:00'); // Monday
		$end = new \DateTime('2024-01-21 23:59:59');   // Sunday
		$this->assertTrue(DatePeriod::isFullWeek($start, $end));

		// Tuesday to Sunday (not a full week)
		$start = new \DateTime('2024-01-16 00:00:00'); // Tuesday
		$end = new \DateTime('2024-01-21 23:59:59');   // Sunday
		$this->assertFalse(DatePeriod::isFullWeek($start, $end));

		// Monday to Saturday (not a full week)
		$start = new \DateTime('2024-01-15 00:00:00'); // Monday
		$end = new \DateTime('2024-01-20 23:59:59');   // Saturday
		$this->assertFalse(DatePeriod::isFullWeek($start, $end));

		// Monday to Friday (only 4 days difference)
		$start = new \DateTime('2024-01-15 00:00:00'); // Monday
		$end = new \DateTime('2024-01-19 23:59:59');   // Friday
		$this->assertFalse(DatePeriod::isFullWeek($start, $end));
	}

	public function testIsFullWeeks(): void
	{
		// Monday to Sunday (1 week)
		$start = new \DateTime('2024-01-15'); // Monday
		$end = new \DateTime('2024-01-21');   // Sunday
		$this->assertTrue(DatePeriod::isFullWeeks($start, $end));

		// Monday to Sunday (2 weeks)
		$start = new \DateTime('2024-01-15'); // Monday
		$end = new \DateTime('2024-01-28');   // Sunday
		$this->assertTrue(DatePeriod::isFullWeeks($start, $end));

		// Monday 1st to Sunday 28th January 2024 (4 full weeks)
		$start = new \DateTime('2024-01-01'); // Monday
		$end = new \DateTime('2024-01-28');   // Sunday
		$this->assertTrue(DatePeriod::isFullWeeks($start, $end));

		// Tuesday to Sunday
		$start = new \DateTime('2024-01-16'); // Tuesday
		$end = new \DateTime('2024-01-21');   // Sunday
		$this->assertFalse(DatePeriod::isFullWeeks($start, $end));
	}

	public function testIsFullMonth(): void
	{
		// Full month (January 2024)
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-01-31');
		$this->assertTrue(DatePeriod::isFullMonth($start, $end));

		// February (leap year)
		$start = new \DateTime('2024-02-01');
		$end = new \DateTime('2024-02-29');
		$this->assertTrue(DatePeriod::isFullMonth($start, $end));

		// February (non-leap year)
		$start = new \DateTime('2023-02-01');
		$end = new \DateTime('2023-02-28');
		$this->assertTrue(DatePeriod::isFullMonth($start, $end));

		// Not a full month (starts on 2nd)
		$start = new \DateTime('2024-01-02');
		$end = new \DateTime('2024-01-31');
		$this->assertFalse(DatePeriod::isFullMonth($start, $end));

		// Not a full month (ends on 30th)
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-01-30');
		$this->assertFalse(DatePeriod::isFullMonth($start, $end));

		// Multiple months (1st January to 31st March)
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-03-31');
		$this->assertFalse(DatePeriod::isFullMonth($start, $end)); // isFullMonth checks for a SINGLE month
	}

	public function testIsFullMonths(): void
	{
		// 2 full months
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-02-29');
		$this->assertTrue(DatePeriod::isFullMonths($start, $end));

		// January to February (non-leap year)
		$start = new \DateTime('2023-01-01');
		$end = new \DateTime('2023-02-28');
		$this->assertTrue(DatePeriod::isFullMonths($start, $end));

		// Starts on 2nd
		$start = new \DateTime('2024-01-02');
		$end = new \DateTime('2024-02-29');
		$this->assertFalse(DatePeriod::isFullMonths($start, $end));

		// Ends on 28th instead of 29th
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-02-28');
		$this->assertFalse(DatePeriod::isFullMonths($start, $end));
	}

	public function testIsFullYear(): void
	{
		// Full year 2024
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-12-31');
		$this->assertTrue(DatePeriod::isFullYear($start, $end));

		// Not a full year (starts on 2nd)
		$start = new \DateTime('2024-01-02');
		$end = new \DateTime('2024-12-31');
		$this->assertFalse(DatePeriod::isFullYear($start, $end));

		// Not a full year (ends on 30th)
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-12-30');
		$this->assertFalse(DatePeriod::isFullYear($start, $end));

		// Two different years
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2025-12-31');
		$this->assertFalse(DatePeriod::isFullYear($start, $end));
	}

	public function testGetYearFromStartDateAndEndDate(): void
	{
		// Valid full year
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-12-31');
		$this->assertEquals(2024, DatePeriod::getYearFromStartDateAndEndDate($start, $end));

		// Not a full year (starts on 2nd)
		$start = new \DateTime('2024-01-02');
		$end = new \DateTime('2024-12-31');
		$this->assertNull(DatePeriod::getYearFromStartDateAndEndDate($start, $end));

		// Not a full year (ends on 30th)
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-12-30');
		$this->assertNull(DatePeriod::getYearFromStartDateAndEndDate($start, $end));

		// Different years
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2025-12-31');
		$this->assertNull(DatePeriod::getYearFromStartDateAndEndDate($start, $end));

		// Mid-year (1st January to 30th June)
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-06-30');
		$this->assertNull(DatePeriod::getYearFromStartDateAndEndDate($start, $end));
	}

	// ========== Period Operations ==========

	public function testContains(): void
	{
		$periodStart = new \DateTime('2024-01-15');
		$periodEnd = new \DateTime('2024-01-20');

		// Date within period
		$date = new \DateTime('2024-01-17');
		$this->assertTrue(DatePeriod::contains($date, $periodStart, $periodEnd));

		// Date at start boundary
		$date = new \DateTime('2024-01-15');
		$this->assertTrue(DatePeriod::contains($date, $periodStart, $periodEnd));

		// Date at end boundary
		$date = new \DateTime('2024-01-20');
		$this->assertTrue(DatePeriod::contains($date, $periodStart, $periodEnd));

		// Date before period
		$date = new \DateTime('2024-01-10');
		$this->assertFalse(DatePeriod::contains($date, $periodStart, $periodEnd));

		// Date after period
		$date = new \DateTime('2024-01-25');
		$this->assertFalse(DatePeriod::contains($date, $periodStart, $periodEnd));
	}

	public function testOverlaps(): void
	{
		// Overlapping periods
		$p1Start = new \DateTime('2024-01-15');
		$p1End = new \DateTime('2024-01-20');
		$p2Start = new \DateTime('2024-01-18');
		$p2End = new \DateTime('2024-01-25');
		$this->assertTrue(DatePeriod::overlaps($p1Start, $p1End, $p2Start, $p2End));

		// Touching periods (end = start)
		$p1Start = new \DateTime('2024-01-15');
		$p1End = new \DateTime('2024-01-20');
		$p2Start = new \DateTime('2024-01-20');
		$p2End = new \DateTime('2024-01-25');
		$this->assertTrue(DatePeriod::overlaps($p1Start, $p1End, $p2Start, $p2End));

		// Non-overlapping periods
		$p1Start = new \DateTime('2024-01-15');
		$p1End = new \DateTime('2024-01-20');
		$p2Start = new \DateTime('2024-01-25');
		$p2End = new \DateTime('2024-01-30');
		$this->assertFalse(DatePeriod::overlaps($p1Start, $p1End, $p2Start, $p2End));

		// Period 2 completely contains period 1
		$p1Start = new \DateTime('2024-01-17');
		$p1End = new \DateTime('2024-01-19');
		$p2Start = new \DateTime('2024-01-15');
		$p2End = new \DateTime('2024-01-25');
		$this->assertTrue(DatePeriod::overlaps($p1Start, $p1End, $p2Start, $p2End));
	}

	public function testGetOverlap(): void
	{
		// Overlapping periods
		$p1Start = new \DateTime('2024-01-15');
		$p1End = new \DateTime('2024-01-20');
		$p2Start = new \DateTime('2024-01-18');
		$p2End = new \DateTime('2024-01-25');

		$overlap = DatePeriod::getOverlap($p1Start, $p1End, $p2Start, $p2End);
		$this->assertIsArray($overlap);
		$this->assertArrayHasKey('start', $overlap);
		$this->assertArrayHasKey('end', $overlap);
		$this->assertEquals('2024-01-18', $overlap['start']->format('Y-m-d'));
		$this->assertEquals('2024-01-20', $overlap['end']->format('Y-m-d'));

		// Non-overlapping periods
		$p1Start = new \DateTime('2024-01-15');
		$p1End = new \DateTime('2024-01-20');
		$p2Start = new \DateTime('2024-01-25');
		$p2End = new \DateTime('2024-01-30');
		$overlap = DatePeriod::getOverlap($p1Start, $p1End, $p2Start, $p2End);
		$this->assertNull($overlap);

		// Period 2 completely contains period 1
		$p1Start = new \DateTime('2024-01-17');
		$p1End = new \DateTime('2024-01-19');
		$p2Start = new \DateTime('2024-01-15');
		$p2End = new \DateTime('2024-01-25');
		$overlap = DatePeriod::getOverlap($p1Start, $p1End, $p2Start, $p2End);
		$this->assertIsArray($overlap);
		$this->assertEquals('2024-01-17', $overlap['start']->format('Y-m-d'));
		$this->assertEquals('2024-01-19', $overlap['end']->format('Y-m-d'));
	}

	public function testSplit(): void
	{
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-01-10');

		// Split into 2 parts
		$parts = DatePeriod::split($start, $end, 2);
		$this->assertCount(2, $parts);
		$this->assertArrayHasKey('start', $parts[0]);
		$this->assertArrayHasKey('end', $parts[0]);
		$this->assertInstanceOf(\DateTime::class, $parts[0]['start']);
		$this->assertInstanceOf(\DateTime::class, $parts[0]['end']);

		// Split into 3 parts
		$parts = DatePeriod::split($start, $end, 3);
		$this->assertCount(3, $parts);

		// Verify continuity (end of part N + 1 day = start of part N+1)
		$this->assertEquals(
			$parts[0]['end']->format('Y-m-d'),
			(clone $parts[1]['start'])->modify('-1 day')->format('Y-m-d')
		);

		// Split into 0 parts (invalid)
		$parts = DatePeriod::split($start, $end, 0);
		$this->assertEmpty($parts);

		// Split into negative parts (invalid)
		$parts = DatePeriod::split($start, $end, -1);
		$this->assertEmpty($parts);
	}

	public function testAssignLanes(): void
	{
		// Two non-overlapping intervals: both get lane 0
		$intervals = [
			'a' => ['start' => new \DateTime('2024-01-15 08:00'), 'end' => new \DateTime('2024-01-15 12:00')],
			'b' => ['start' => new \DateTime('2024-01-15 14:00'), 'end' => new \DateTime('2024-01-15 18:00')],
		];
		$lanes = DatePeriod::assignLanes($intervals);
		$this->assertEquals(0, $lanes['a']);
		$this->assertEquals(0, $lanes['b']);
		$this->assertEquals(1, DatePeriod::getNbLanes($lanes));

		// Two overlapping intervals: different lanes
		$intervals = [
			'a' => ['start' => new \DateTime('2024-01-15 08:00'), 'end' => new \DateTime('2024-01-15 12:00')],
			'b' => ['start' => new \DateTime('2024-01-15 10:00'), 'end' => new \DateTime('2024-01-15 14:00')],
		];
		$lanes = DatePeriod::assignLanes($intervals);
		$this->assertNotEquals($lanes['a'], $lanes['b']);
		$this->assertEquals(2, DatePeriod::getNbLanes($lanes));

		// Three intervals: a and c overlap b, but not each other => a and c can share a lane
		$intervals = [
			'a' => ['start' => new \DateTime('2024-01-15 08:00'), 'end' => new \DateTime('2024-01-15 10:00')],
			'b' => ['start' => new \DateTime('2024-01-15 09:00'), 'end' => new \DateTime('2024-01-15 12:00')],
			'c' => ['start' => new \DateTime('2024-01-15 11:00'), 'end' => new \DateTime('2024-01-15 13:00')],
		];
		$lanes = DatePeriod::assignLanes($intervals);
		$this->assertEquals($lanes['a'], $lanes['c']);
		$this->assertNotEquals($lanes['a'], $lanes['b']);
		$this->assertEquals(2, DatePeriod::getNbLanes($lanes));

		// Original array keys are preserved, including non-sequential/string keys
		$this->assertEqualsCanonicalizing(['a', 'b', 'c'], array_keys($lanes));

		// Empty input
		$this->assertSame([], DatePeriod::assignLanes([]));
		$this->assertEquals(0, DatePeriod::getNbLanes([]));
	}

	public function testHasWeekendDayBetween(): void
	{
		// Same day — nothing strictly between
		$date = new \DateTime('2024-01-15'); // Monday
		$this->assertFalse(DatePeriod::hasWeekendDayBetween($date, $date));

		// Adjacent days — nothing strictly between
		$from = new \DateTime('2024-01-15'); // Monday
		$to   = new \DateTime('2024-01-16'); // Tuesday
		$this->assertFalse(DatePeriod::hasWeekendDayBetween($from, $to));

		// One day strictly between — weekday only (Wed between Tue and Thu)
		$from = new \DateTime('2024-01-16'); // Tuesday
		$to   = new \DateTime('2024-01-18'); // Thursday
		$this->assertFalse(DatePeriod::hasWeekendDayBetween($from, $to));

		// Several weekdays strictly between, no weekend (Mon to Sat → Tue–Fri in between)
		$from = new \DateTime('2024-01-15'); // Monday
		$to   = new \DateTime('2024-01-20'); // Saturday
		$this->assertFalse(DatePeriod::hasWeekendDayBetween($from, $to));

		// Boundary on Saturday and Sunday — strictly between is empty
		$from = new \DateTime('2024-01-20'); // Saturday
		$to   = new \DateTime('2024-01-21'); // Sunday
		$this->assertFalse(DatePeriod::hasWeekendDayBetween($from, $to));

		// One day strictly between — Saturday (Fri to Sun)
		$from = new \DateTime('2024-01-19'); // Friday
		$to   = new \DateTime('2024-01-21'); // Sunday
		$this->assertTrue(DatePeriod::hasWeekendDayBetween($from, $to));

		// One day strictly between — Sunday (Sat to Mon)
		$from = new \DateTime('2024-01-20'); // Saturday
		$to   = new \DateTime('2024-01-22'); // Monday
		$this->assertTrue(DatePeriod::hasWeekendDayBetween($from, $to));

		// Exactly 6 days strictly between (Mon to Mon+7) — early-exit optimization
		$from = new \DateTime('2024-01-15'); // Monday
		$to   = new \DateTime('2024-01-22'); // Monday (strictly between: Tue–Sun = 6 days)
		$this->assertTrue(DatePeriod::hasWeekendDayBetween($from, $to));

		// Long period spanning several weeks
		$from = new \DateTime('2024-01-01');
		$to   = new \DateTime('2024-01-31');
		$this->assertTrue(DatePeriod::hasWeekendDayBetween($from, $to));

		// Reversed dates ($from > $to) — nothing strictly between
		$from = new \DateTime('2024-01-20');
		$to   = new \DateTime('2024-01-15');
		$this->assertFalse(DatePeriod::hasWeekendDayBetween($from, $to));

		// Time components are ignored — only dates matter
		$from = new \DateTime('2024-01-19 23:59:59'); // Friday evening
		$to   = new \DateTime('2024-01-21 00:00:01'); // Sunday morning
		$this->assertTrue(DatePeriod::hasWeekendDayBetween($from, $to));
	}

	// ========== Labeling Methods ==========

	public function testGetLabel(): void
	{
		// Same day
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-15');
		$label = DatePeriod::getLabel($start, $end);
		$this->assertStringContainsString('le', $label);
		$this->assertStringContainsString('15', $label);

		// Same day - specific test
		$date = new \DateTime('2024-03-15');
		$label = DatePeriod::getLabel($date, $date);
		$this->assertStringContainsString('le', $label);
		$this->assertStringContainsString('15', $label);
		$this->assertStringContainsString('mars', strtolower($label));
		$this->assertStringContainsString('2024', $label);

		// Full month
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-01-31');
		$label = DatePeriod::getLabel($start, $end);
		$this->assertStringContainsStringIgnoringCase('janvier', $label);
		$this->assertStringContainsString('2024', $label);

		// Full month - March
		$start = new \DateTime('2024-03-01');
		$end = new \DateTime('2024-03-31');
		$label = DatePeriod::getLabel($start, $end);
		$this->assertStringContainsString('en', $label);
		$this->assertStringContainsString('mars', strtolower($label));
		$this->assertStringContainsString('2024', $label);

		// Full year
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-12-31');
		$label = DatePeriod::getLabel($start, $end);
		$this->assertStringContainsString('en', $label);
		$this->assertStringContainsString('2024', $label);
		$this->assertStringNotContainsString('du', $label);

		// Arbitrary period
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-03-20');
		$label = DatePeriod::getLabel($start, $end);
		$this->assertStringContainsString('du', $label);
		$this->assertStringContainsString('au', $label);

		// Different years
		$start = new \DateTime('2024-12-15');
		$end = new \DateTime('2025-01-15');
		$label = DatePeriod::getLabel($start, $end);
		$this->assertStringContainsString('du', $label);
		$this->assertStringContainsString('au', $label);
		$this->assertStringContainsString('2024', $label);
		$this->assertStringContainsString('2025', $label);

		// Same year, multiple months
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-03-20');
		$label = DatePeriod::getLabel($start, $end);
		$this->assertStringContainsString('du', $label);
		$this->assertStringContainsString('au', $label);

		// Partial month
		$start = new \DateTime('2024-01-10');
		$end = new \DateTime('2024-01-20');
		$label = DatePeriod::getLabel($start, $end);
		$this->assertStringContainsString('du', $label);
		$this->assertStringContainsString('au', $label);
	}

	/* getLabelEn() */

	public function testGetLabelEnSingleDay(): void
	{
		// Single day (January 15, 2024)
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-15');
		$label = DatePeriod::getLabel($start, $end, 'en');
		$this->assertEquals('January 15, 2024', $label);
	}

	public function testGetLabelEnFullMonth(): void
	{
		// Full month (January 2024)
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-01-31');
		$label = DatePeriod::getLabel($start, $end, 'en');
		$this->assertEquals('January 2024', $label);
	}

	public function testGetLabelEnFullYear(): void
	{
		// Full year (2024)
		$start = new \DateTime('2024-01-01');
		$end = new \DateTime('2024-12-31');
		$label = DatePeriod::getLabel($start, $end, 'en');
		$this->assertEquals('2024', $label);
	}

	public function testGetLabelEnNormalPeriod(): void
	{
		// Normal multi-day period (January 15 to March 20, 2024)
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-03-20');
		$label = DatePeriod::getLabel($start, $end, 'en');
		$this->assertEquals('January 15, 2024 to March 20, 2024', $label);
	}

	public function testGetLabelEnCrossYear(): void
	{
		// Cross-year period (December 15, 2023 to January 10, 2024)
		$start = new \DateTime('2023-12-15');
		$end = new \DateTime('2024-01-10');
		$label = DatePeriod::getLabel($start, $end, 'en');
		$this->assertEquals('December 15, 2023 to January 10, 2024', $label);
	}

	// ========== Generic Utilities ==========

	public function testGetPeriodUnits(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-03-15');

		// Test HOUR - should return null (static type, use getPeriodUnitsFormatted instead)
		$result = DatePeriod::getPeriodUnits(PeriodType::HOUR, $start, $end);
		$this->assertNull($result);

		// Test DAY_OF_MONTH - should return DateTime[]
		$result = DatePeriod::getPeriodUnits(PeriodType::DAY_OF_MONTH, $start, $end);
		$this->assertIsArray($result);
		$this->assertGreaterThan(0, count($result));
		$this->assertInstanceOf(\DateTime::class, $result[0]);

		// Test WEEK - should return DateTime[]
		$result = DatePeriod::getPeriodUnits(PeriodType::WEEK, $start, $end);
		$this->assertIsArray($result);
		$this->assertGreaterThan(0, count($result));
		$this->assertInstanceOf(\DateTime::class, $result[0]);

		// Test MONTH - should return DateTime[]
		$result = DatePeriod::getPeriodUnits(PeriodType::MONTH, $start, $end);
		$this->assertIsArray($result);
		$this->assertGreaterThan(0, count($result));
		$this->assertInstanceOf(\DateTime::class, $result[0]);

		// Test YEAR - should return DateTime[] (Jan 1st of each year)
		$startYear = new \DateTime('2023-03-15');
		$endYear = new \DateTime('2025-10-20');
		$result = DatePeriod::getPeriodUnits(PeriodType::YEAR, $startYear, $endYear);
		$this->assertIsArray($result);
		$this->assertCount(3, $result); // 2023, 2024, 2025
		$this->assertInstanceOf(\DateTime::class, $result[0]);
		$this->assertEquals('2023-01-01', $result[0]->format('Y-m-d'));
		$this->assertEquals('2024-01-01', $result[1]->format('Y-m-d'));
		$this->assertEquals('2025-01-01', $result[2]->format('Y-m-d'));

		// Test DAY_OF_WEEK - should return null (static type, use getPeriodUnitsFormatted instead)
		$result = DatePeriod::getPeriodUnits(PeriodType::DAY_OF_WEEK, $start, $end);
		$this->assertNull($result);
	}

	public function testGetPeriodUnitsFormatted(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-03-15');

		// Test HOUR - should return string[]
		$result = DatePeriod::getPeriodUnitsFormatted(PeriodType::HOUR, $start, $end);
		$this->assertIsArray($result);
		$this->assertCount(24, $result);
		$this->assertContains('0', $result);
		$this->assertContains('23', $result);

		// Test DAY_OF_MONTH - should return string[] with date format
		$result = DatePeriod::getPeriodUnitsFormatted(PeriodType::DAY_OF_MONTH, $start, $end);
		$this->assertIsArray($result);
		$this->assertGreaterThan(0, count($result));
		$this->assertIsString($result[0]);
		$this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $result[0]);

		// Test WEEK - should return string[] with week format
		$result = DatePeriod::getPeriodUnitsFormatted(PeriodType::WEEK, $start, $end);
		$this->assertIsArray($result);
		$this->assertGreaterThan(0, count($result));
		$this->assertIsString($result[0]);
		$this->assertMatchesRegularExpression('/\d{4}-\d{2}/', $result[0]);

		// Test MONTH - should return string[] with month format
		$result = DatePeriod::getPeriodUnitsFormatted(PeriodType::MONTH, $start, $end);
		$this->assertIsArray($result);
		$this->assertGreaterThan(0, count($result));
		$this->assertIsString($result[0]);

		// Test DAY_OF_WEEK - should return string[]
		$result = DatePeriod::getPeriodUnitsFormatted(PeriodType::DAY_OF_WEEK, $start, $end);
		$this->assertIsArray($result);
		$this->assertCount(7, $result);
		$this->assertContains('1', $result);
		$this->assertContains('7', $result);

		// Test YEAR - should return string[] with year format
		$startYear = new \DateTime('2023-03-15');
		$endYear = new \DateTime('2025-10-20');
		$result = DatePeriod::getPeriodUnitsFormatted(PeriodType::YEAR, $startYear, $endYear);
		$this->assertIsArray($result);
		$this->assertCount(3, $result); // 2023, 2024, 2025
		$this->assertIsString($result[0]);
		$this->assertEquals('2023', $result[0]);
		$this->assertEquals('2024', $result[1]);
		$this->assertEquals('2025', $result[2]);
	}

	// ========== Deprecated Methods Tests ==========

	public function testGetListOfDateDaysOfTheMonth(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-17');

		$days = DatePeriod::getListOfDateDaysOfTheMonth($start, $end);
		$this->assertCount(3, $days);
		$this->assertInstanceOf(\DateTime::class, $days[0]);
	}

	public function testGetListOfDaysOfTheMonth(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-17');

		$days = DatePeriod::getListOfDaysOfTheMonth($start, $end);
		$this->assertEquals(['2024-01-15', '2024-01-16', '2024-01-17'], $days);
	}

	public function testGetListOfDateWeeks(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-02-15');

		$weeks = DatePeriod::getListOfDateWeeks($start, $end);
		$this->assertIsArray($weeks);
		$this->assertGreaterThan(0, count($weeks));
		$this->assertInstanceOf(\DateTime::class, $weeks[0]);
	}

	public function testGetListOfWeeks(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-02-15');

		$weeks = DatePeriod::getListOfWeeks($start, $end);
		$this->assertIsArray($weeks);
		$this->assertGreaterThan(0, count($weeks));
	}

	public function testGetListOfDateMonths(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-04-15');

		$months = DatePeriod::getListOfDateMonths($start, $end);
		$this->assertIsArray($months);
		$this->assertGreaterThan(0, count($months));
		$this->assertInstanceOf(\DateTime::class, $months[0]);
	}

	public function testGetListOfMonths(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-03-15');

		$months = DatePeriod::getListOfMonths($start, $end);
		$this->assertGreaterThan(0, count($months));
		$this->assertIsString($months[0]);
	}

	public function testGetListDaysOfMonths(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-01-17');

		$days = DatePeriod::getListDaysOfMonths($start, $end);
		$this->assertCount(3, $days);
		$this->assertInstanceOf(\DateTime::class, $days[0]);
	}

	public function testGetNbDaysBetweenDatesAndTimes(): void
	{
		$start = new \DateTime('2024-01-15 10:00:00');
		$end = new \DateTime('2024-01-15 14:00:00');
		$result = DatePeriod::getNbDaysBetweenDatesAndTimes($start, $end);
		$this->assertLessThan(1, $result);
	}

	public function testGetListOfPeriod(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-03-15');

		// Test deprecated method calls getPeriodUnits() which returns null for static types
		$result = DatePeriod::getListOfPeriod(PeriodType::HOUR, $start, $end);
		$this->assertNull($result); // HOUR returns null (use getListOfPeriodFormatted instead)

		// Test date-based types return DateTime[]
		$result = DatePeriod::getListOfPeriod(PeriodType::DAY_OF_MONTH, $start, $end);
		$this->assertIsArray($result);
		$this->assertGreaterThan(0, count($result));
		$this->assertInstanceOf(\DateTime::class, $result[0]);
	}

	public function testGetListOfPeriodFormatted(): void
	{
		$start = new \DateTime('2024-01-15');
		$end = new \DateTime('2024-03-15');

		// Test deprecated method should work like getPeriodsFormatted()
		$result = DatePeriod::getListOfPeriodFormatted(PeriodType::HOUR, $start, $end);
		$this->assertIsArray($result);
		$this->assertCount(24, $result);

		$result = DatePeriod::getListOfPeriodFormatted(PeriodType::DAY_OF_MONTH, $start, $end);
		$this->assertIsArray($result);
		$this->assertGreaterThan(0, count($result));
		$this->assertIsString($result[0]);
	}
}