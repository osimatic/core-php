<?php

namespace Osimatic\Calendar;

/**
 * Utility class for date period calculations and manipulations.
 * This class provides comprehensive methods for working with date ranges and periods, including calculations for days, weeks, months, and years. It handles period validation, list generation, and formatting operations.
 * Organized categories:
 * - Constants: Standard date format constants
 * - Counting: Calculate number of days, weeks, months, years in a period
 * - List Generation: Generate lists of dates, weeks, months within periods
 * - Validation: Check if periods represent full weeks, months, or years
 * - Period Operations: Check containment, overlaps, and split periods
 * - Timeline Layout: Assign lane indices to overlapping intervals for Gantt-like visual layouts
 * - Labeling: Generate human-readable period labels
 * - Generic Utilities: Period-based list generation with type support
 */
class DatePeriod
{
	// ========== Format Constants ==========

	/**
	 * Standard date format (YYYY-MM-DD)
	 */
	public const string FORMAT_DATE = 'Y-m-d';

	/**
	 * ISO 8601 week format (YYYY-WNN)
	 */
	public const string FORMAT_WEEK_ISO = 'Y-W';

	/**
	 * Year-month format without leading zeros (YYYY-M)
	 */
	public const string FORMAT_MONTH = 'Y-n';

	/**
	 * Year format (YYYY)
	 */
	public const string FORMAT_YEAR = 'Y';

	// ========== Counting Methods ==========

	/**
	 * Calculates the number of days between two dates.
	 * By default, ignores time components and calculates full days. Set $withTimes to true to include time in the calculation, which may result in fractional days being rounded.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @param bool $withTimes Whether to include time components in calculation (default: false, time is ignored and set to midnight)
	 * @return int The number of days in the period (can be negative if end is before start)
	 */
	public static function getNbDays(\DateTime $periodStart, \DateTime $periodEnd, bool $withTimes=false): int
	{
		try {
			if (!$withTimes) {
				$periodStart = new \DateTime($periodStart->format('Y-m-d').' 00:00:00');
				$periodEnd = new \DateTime($periodEnd->format('Y-m-d').' 00:00:00');
			}
			return (int) $periodStart->diff($periodEnd)->format('%r%a');
		} catch (\Exception) {}
		return 0;
	}

	/**
	 * Calculates the remaining days in a period (days component only, excluding months and years).
	 * This returns only the day component of the date difference, useful for getting the remainder after accounting for full months. For example, if the difference is 2 months and 5 days, this returns 5.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return int The number of remaining days (day component only from DateInterval->d)
	 */
	public static function getNbRemainingDays(\DateTime $periodStart, \DateTime $periodEnd): int
	{
		try {
			$periodStart = new \DateTime($periodStart->format('Y-m-d').' 00:00:00');
			$periodEnd = new \DateTime($periodEnd->format('Y-m-d').' 00:00:00');
			return (int) $periodStart->diff($periodEnd)->d;
		} catch (\Exception) {}
		return 0;
	}

	/**
	 * Calculates the number of complete weeks in a period.
	 * Divides the total number of days by 7 and returns the floor value, giving the count of full 7-day weeks.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return int The number of complete 7-day weeks in the period
	 */
	public static function getNbFullWeeks(\DateTime $periodStart, \DateTime $periodEnd): int
	{
		return (int) floor(self::getNbDays($periodStart, $periodEnd) / 7);
	}

	/**
	 * Calculates the number of calendar weeks spanned by a period.
	 * Counts distinct calendar weeks (Monday-Sunday) that the period touches, even partially. This differs from getNbFullWeeks() which only counts complete 7-day periods.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return int The number of calendar weeks in the period
	 */
	public static function getNbWeeks(\DateTime $periodStart, \DateTime $periodEnd): int
	{
		return count(self::getWeeksFormatted($periodStart, $periodEnd));
	}

	/**
	 * Calculates the number of complete months between two dates.
	 * Counts full month boundaries crossed, considering years as well. For example, from Jan 15 to March 10 returns 1 (Feb is the only complete month between them).
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return int The number of full months in the period (years * 12 + months)
	 */
	public static function getNbFullMonths(\DateTime $periodStart, \DateTime $periodEnd): int
	{
		try {
			$startDate = new \DateTime($periodStart->format('Y-m-d').' 00:00:00');
			$endDate = new \DateTime($periodEnd->format('Y-m-d').' 00:00:00');
			$dateInterval = $startDate->diff($endDate);
			return (($dateInterval->y) * 12) + ($dateInterval->m);
		} catch (\Exception) {}
		return 0;
	}

	/**
	 * Calculates the number of distinct calendar months spanned by a period.
	 * Counts distinct months that the period touches, even partially. From Jan 15 to March 10 returns 3 (Jan, Feb, Mar).
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return int The number of calendar months touched by the period
	 */
	public static function getNbMonths(\DateTime $periodStart, \DateTime $periodEnd): int
	{
		return count(self::getMonthsFormatted($periodStart, $periodEnd));
	}

	/**
	 * Calculates the approximate number of years between two dates.
	 * Uses an average year length of 365.25 days to account for leap years. This is an approximation and may not match exact calendar year boundaries.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return int The approximate number of years in the period (floored)
	 */
	public static function getNbYears(\DateTime $periodStart, \DateTime $periodEnd): int
	{
		$start = (clone $periodStart)->setTime(0, 0, 0);
		$end = (clone $periodEnd)->setTime(0, 0, 0);
		$nbSeconds = $end->getTimestamp() - $start->getTimestamp();
		return (int) floor($nbSeconds / (3600 * 24 * 365.25));
	}

	// ========== List Generation Methods ==========

	/**
	 * Generates a list of DateTime objects for each day in a period.
	 * Creates a DateTime object for each day from start to end (inclusive). Optionally filters by specific weekdays using ISO-8601 numeric representation (1=Monday, 7=Sunday).
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @param int[]|null $weekDays Optional array of ISO-8601 weekday numbers to filter (1=Monday, 2=Tuesday, ..., 7=Sunday). If null, includes all days.
	 * @return \DateTime[] Array of DateTime objects for each day in the period (optionally filtered by weekdays)
	 */
	public static function getDays(\DateTime $periodStart, \DateTime $periodEnd, ?array $weekDays=null): array
	{
		$startIntervalDate = (clone $periodStart)->setTime(0, 0, 0);
		$endIntervalDate = (clone $periodEnd)->setTime(0, 0, 0)->modify('+1 day');

		$list = self::generateDatePeriodList($startIntervalDate, $endIntervalDate, 'P1D');

		if (null !== $weekDays) {
			return array_values(array_filter($list, static fn(\DateTime $date) => in_array((int) $date->format('N'), $weekDays, true)));
		}

		return $list;
	}

	/**
	 * Generates a list of formatted date strings for each day in a period.
	 * Returns formatted date strings instead of DateTime objects. Useful for generating date lists for display or API responses.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @param int[]|null $weekDays Optional array of ISO-8601 weekday numbers to filter (1=Monday through 7=Sunday)
	 * @param string $dateFormat PHP date format string (default: 'Y-m-d')
	 * @return string[] Array of formatted date strings
	 */
	public static function getDaysFormatted(\DateTime $periodStart, \DateTime $periodEnd, ?array $weekDays=null, string $dateFormat=self::FORMAT_DATE): array
	{
		return array_map(static fn(\DateTime $date) => $date->format($dateFormat), self::getDays($periodStart, $periodEnd, $weekDays));
	}

	/**
	 * Generates a list of DateTime objects for the Monday of each week in a period.
	 * Returns the Monday of each calendar week (ISO 8601 week definition) that overlaps with the period. Even if the period starts mid-week, the Monday of that week is included.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return \DateTime[] Array of DateTime objects, each set to the Monday of a week in the period
	 */
	public static function getWeeks(\DateTime $periodStart, \DateTime $periodEnd): array
	{
		$startIntervalDate = (clone $periodStart)->modify('Monday this week');
		$endIntervalDate = (clone $periodEnd)->modify('this Sunday');

		return self::generateDatePeriodList($startIntervalDate, $endIntervalDate, 'P1W');
	}

	/**
	 * Generates a list of formatted week strings for each week in a period.
	 * Returns ISO 8601 week representations by default (format: YYYY-WW, e.g., "2024-W15"). The format can be customized using any PHP date format string.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @param string $dateFormat PHP date format string (default: 'Y-W' for ISO 8601 week format)
	 * @return string[] Array of formatted week strings
	 */
	public static function getWeeksFormatted(\DateTime $periodStart, \DateTime $periodEnd, string $dateFormat=self::FORMAT_WEEK_ISO): array
	{
		return array_map(static fn(\DateTime $date) => $date->format($dateFormat), self::getWeeks($periodStart, $periodEnd));
	}

	/**
	 * Generates a list of DateTime objects for the first day of each month in a period.
	 * Returns DateTime objects set to the 1st day of each month that the period touches. If the period spans from Jan 15 to March 20, returns DateTime objects for Jan 1, Feb 1, and Mar 1.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return \DateTime[] Array of DateTime objects, each set to the first day of a month in the period
	 */
	public static function getMonths(\DateTime $periodStart, \DateTime $periodEnd): array
	{
		$startIntervalDate = (clone $periodStart)->modify('first day of this month');
		$endIntervalDate = (clone $periodEnd)->modify('last day of this month');

		return self::generateDatePeriodList($startIntervalDate, $endIntervalDate, 'P1M');
	}

	/**
	 * Generates a list of formatted month strings for each month in a period.
	 * Returns year-month representations (default format: "2024-1", "2024-2", etc.). The format can be customized using any PHP date format string.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @param string $dateFormat PHP date format string (default: 'Y-n' for year and numeric month without leading zeros)
	 * @return string[] Array of formatted month strings
	 */
	public static function getMonthsFormatted(\DateTime $periodStart, \DateTime $periodEnd, string $dateFormat=self::FORMAT_MONTH): array
	{
		return array_map(static fn(\DateTime $date) => $date->format($dateFormat), self::getMonths($periodStart, $periodEnd));
	}

	/**
	 * Generates a list of DateTime objects for January 1st of each year in a period.
	 * Returns DateTime objects set to January 1st of each year that the period touches. If the period spans from March 2023 to October 2025, returns DateTime objects for Jan 1, 2023, Jan 1, 2024, and Jan 1, 2025.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return \DateTime[] Array of DateTime objects, each set to January 1st of a year in the period
	 */
	public static function getYears(\DateTime $periodStart, \DateTime $periodEnd): array
	{
		$startIntervalDate = (clone $periodStart)->modify('first day of January this year');
		$endIntervalDate = (clone $periodEnd)->modify('last day of December this year');

		return self::generateDatePeriodList($startIntervalDate, $endIntervalDate, 'P1Y');
	}

	/**
	 * Generates a list of formatted year strings for each year in a period.
	 * Returns year representations (default format: "2024", "2025", etc.). The format can be customized using any PHP date format string.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @param string $dateFormat PHP date format string (default: 'Y' for 4-digit year)
	 * @return string[] Array of formatted year strings
	 */
	public static function getYearsFormatted(\DateTime $periodStart, \DateTime $periodEnd, string $dateFormat=self::FORMAT_YEAR): array
	{
		return array_map(static fn(\DateTime $date) => $date->format($dateFormat), self::getYears($periodStart, $periodEnd));
	}

	/**
	 * Creates DatePeriod objects from an array of time ranges for a specific date.
	 * Converts pairs of time strings (start/end times in SQL format "HH:MM:SS") into DatePeriod objects by applying them to a reference date. Automatically handles cases where the end time is before the start time (indicating the period spans midnight) by extending the end date to the next day.
	 *
	 * Usage example:
	 * <code>
	 * $timeRanges = [
	 *     ['09:00:00', '12:00:00'],  // Morning shift
	 *     ['14:00:00', '18:00:00'],  // Afternoon shift
	 *     ['22:00:00', '02:00:00'],  // Night shift (spans midnight)
	 * ];
	 * $date = new \DateTime('2024-03-15');
	 * $periods = DatePeriod::createDatePeriodsFromTimeRanges($timeRanges, $date);
	 * // Returns 3 DatePeriod objects:
	 * // - 2024-03-15 09:00:00 to 2024-03-15 12:00:00
	 * // - 2024-03-15 14:00:00 to 2024-03-15 18:00:00
	 * // - 2024-03-15 22:00:00 to 2024-03-16 02:00:00 (next day)
	 * </code>
	 *
	 * @param array<int, array{0: string, 1: string}> $timeRanges Array of time range pairs, where each pair is [startTime, endTime] in SQL time format (HH:MM:SS or HH:MM)
	 * @param \DateTime $referenceDate The reference date to apply the time ranges to
	 * @return \DatePeriod[] Array of DatePeriod objects representing the time ranges on the specified date. Invalid time ranges are silently skipped.
	 * @link https://www.php.net/manual/en/class.dateperiod.php PHP DatePeriod documentation
	 * @link https://www.php.net/manual/en/class.dateinterval.php PHP DateInterval documentation
	 */
	public static function createDatePeriodsFromTimeRanges(array $timeRanges, \DateTime $referenceDate): array
	{
		$datePeriods = [];

		foreach ($timeRanges as $timeRange) {
			// Validate time range structure
			if (!is_array($timeRange) || count($timeRange) !== 2) {
				continue;
			}

			[$startTime, $endTime] = $timeRange;

			// Validate that both times are strings
			if (empty($startTime) || empty($endTime) || !is_string($startTime) || !is_string($endTime)) {
				continue;
			}

			try {
				$startDateTime = new \DateTime($referenceDate->format('Y-m-d') . ' ' . $startTime);
				$endDateTime = clone $referenceDate;

				// If end time is before start time, the period spans midnight to the next day
				if ($startTime > $endTime) {
					$endDateTime->modify('+1 day');
				}

				$endDateTime = new \DateTime($endDateTime->format('Y-m-d') . ' ' . $endTime);

				// Create DatePeriod with 1-day interval (the interval is not really used here as we have explicit start/end)
				$datePeriods[] = new \DatePeriod($startDateTime, new \DateInterval('P1D'), $endDateTime);

			} catch (\Exception $e) {
				// Skip invalid time ranges silently
				// Could log the error here if needed: error_log($e->getMessage());
				continue;
			}
		}

		return $datePeriods;
	}

	// ========== Private Helper Methods ==========

	/**
	 * Generates a list of DateTime objects from a DatePeriod with optional filtering.
	 * Internal helper method to avoid code duplication across getDays(), getWeeks(), getMonths(), and getYears().
	 * @param \DateTime $startIntervalDate The start date for the period
	 * @param \DateTime $endIntervalDate The end date for the period
	 * @param string $interval The DateInterval specification (e.g., 'P1D', 'P1W', 'P1M', 'P1Y')
	 * @return \DateTime[] Array of DateTime objects (optionally filtered)
	 */
	private static function generateDatePeriodList(\DateTime $startIntervalDate, \DateTime $endIntervalDate, string $interval): array
	{
		try {
			$dateRange = new \DatePeriod($startIntervalDate, new \DateInterval($interval), $endIntervalDate);

			$periodList = [];
			foreach ($dateRange as $date) {
				$periodList[] = $date;
			}

			return $periodList;
		}
		catch (\Exception) {
			return [];
		}
	}

	// ========== Validation Methods ==========

	/**
	 * Checks if a period represents exactly one complete calendar week.
	 * Validates that the period starts on a Monday, ends on a Sunday, and spans exactly 6 days (Monday to Sunday inclusive).
	 * @param \DateTime $periodStart The start date to check
	 * @param \DateTime $periodEnd The end date to check
	 * @return bool True if the period is exactly one complete week (Monday to Sunday), false otherwise
	 */
	public static function isFullWeek(\DateTime $periodStart, \DateTime $periodEnd): bool
	{
		return self::getNbDays($periodStart, $periodEnd, true) === 6 && ((int) $periodStart->format('N')) === 1 && ((int) $periodEnd->format('N')) === 7;
	}

	/**
	 * Checks if a period starts on Monday and ends on Sunday (one or more complete weeks).
	 * Unlike isFullWeek(), this allows multiple weeks. It only verifies that the period boundaries align with week boundaries (Monday to Sunday).
	 * @param \DateTime $periodStart The start date to check
	 * @param \DateTime $periodEnd The end date to check
	 * @return bool True if period starts on Monday and ends on Sunday, false otherwise
	 */
	public static function isFullWeeks(\DateTime $periodStart, \DateTime $periodEnd): bool
	{
		return ((int) $periodStart->format('N')) === 1 && ((int) $periodEnd->format('N')) === 7;
	}

	/**
	 * Checks if a period represents exactly one complete calendar month.
	 * Validates that the period starts on the 1st day of a month, ends on the last day of the same month, and both dates are in the same month-year.
	 * @param \DateTime $periodStart The start date to check
	 * @param \DateTime $periodEnd The end date to check
	 * @return bool True if the period is exactly one complete month (from 1st to last day of same month), false otherwise
	 */
	public static function isFullMonth(\DateTime $periodStart, \DateTime $periodEnd): bool
	{
		return $periodStart->format('Ym') === $periodEnd->format('Ym') && ((int) $periodStart->format('d')) === 1 && null !== ($lastDayOfMonth = DateTime::getLastDayOfMonth($periodEnd->format('Y'), $periodEnd->format('m'))) && $lastDayOfMonth->format('d') === $periodEnd->format('d');
	}

	/**
	 * Checks if a period starts on the 1st and ends on the last day of any month(s).
	 * Unlike isFullMonth(), this allows multiple months. It only verifies that the period boundaries align with month boundaries (1st to last day of respective months).
	 * @param \DateTime $periodStart The start date to check
	 * @param \DateTime $periodEnd The end date to check
	 * @return bool True if period starts on 1st of a month and ends on last day of a month, false otherwise
	 */
	public static function isFullMonths(\DateTime $periodStart, \DateTime $periodEnd): bool
	{
		return ((int) $periodStart->format('d')) === 1 && null !== ($lastDayOfMonth = DateTime::getLastDayOfMonth($periodEnd->format('Y'), $periodEnd->format('m'))) && $lastDayOfMonth->format('d') === $periodEnd->format('d');
	}

	/**
	 * Checks if a period represents exactly one complete calendar year.
	 * Validates that the period starts on January 1st, ends on December 31st, and both dates are in the same year.
	 * @param \DateTime $periodStart The start date to check
	 * @param \DateTime $periodEnd The end date to check
	 * @return bool True if the period is exactly one complete year (Jan 1 to Dec 31 of same year), false otherwise
	 */
	public static function isFullYear(\DateTime $periodStart, \DateTime $periodEnd): bool
	{
		return ((int) $periodStart->format('m')) === 1 && ((int) $periodStart->format('d')) === 1 && ((int) $periodEnd->format('m')) === 12 && ((int) $periodEnd->format('d')) === 31 && ((int) $periodStart->format('Y')) === ((int) $periodEnd->format('Y'));
	}

	/**
	 * Extracts the year from a period if it represents a complete calendar year.
	 * Returns the year as an integer if the period spans from January 1 to December 31 of the same year, otherwise returns null.
	 * @param \DateTime $startDate The start date of the period
	 * @param \DateTime $endDate The end date of the period
	 * @return int|null The year if the period is a full year (Jan 1 to Dec 31 of same year), null otherwise
	 */
	public static function getYearFromStartDateAndEndDate(\DateTime $startDate, \DateTime $endDate): ?int
	{
		// Both dates must be in the same year
		if ($startDate->format('Y') !== $endDate->format('Y')) {
			return null;
		}

		// Start date must be January 1st & End date must be December 31st
		if ($startDate->format('m-d') !== '01-01' || $endDate->format('m-d') !== '12-31') {
			return null;
		}

		return (int) $startDate->format('Y');
	}

	// ========== Period Operations ==========

	/**
	 * Checks if a specific date falls within a period.
	 * The check is inclusive, meaning the date can equal either the start or end of the period.
	 * @param \DateTime $date The date to check
	 * @param \DateTime $periodStart The start of the period
	 * @param \DateTime $periodEnd The end of the period
	 * @return bool True if date is within period (inclusive), false otherwise
	 */
	public static function contains(\DateTime $date, \DateTime $periodStart, \DateTime $periodEnd): bool
	{
		$timestamp = $date->getTimestamp();
		return $timestamp >= $periodStart->getTimestamp() && $timestamp <= $periodEnd->getTimestamp();
	}

	/**
	 * Checks if two periods have any overlap.
	 * Returns true if the periods share at least one moment in time, even if they only touch at the boundaries.
	 * @param \DateTime $period1Start Start of first period
	 * @param \DateTime $period1End End of first period
	 * @param \DateTime $period2Start Start of second period
	 * @param \DateTime $period2End End of second period
	 * @return bool True if periods overlap or touch, false otherwise
	 */
	public static function overlaps(\DateTime $period1Start, \DateTime $period1End, \DateTime $period2Start, \DateTime $period2End): bool
	{
		return $period1Start <= $period2End && $period2Start <= $period1End;
	}

	/**
	 * Gets the overlapping period between two periods.
	 * Returns the intersection of two periods as a new period with start and end dates. Returns null if the periods do not overlap.
	 * @param \DateTime $period1Start Start of first period
	 * @param \DateTime $period1End End of first period
	 * @param \DateTime $period2Start Start of second period
	 * @param \DateTime $period2End End of second period
	 * @return array{start: \DateTime, end: \DateTime}|null The overlapping period with 'start' and 'end' keys, or null if no overlap exists
	 */
	public static function getOverlap(\DateTime $period1Start, \DateTime $period1End, \DateTime $period2Start, \DateTime $period2End): ?array
	{
		if (!self::overlaps($period1Start, $period1End, $period2Start, $period2End)) {
			return null;
		}
		return [
			'start' => $period1Start > $period2Start ? clone $period1Start : clone $period2Start,
			'end' => $period1End < $period2End ? clone $period1End : clone $period2End,
		];
	}

	/**
	 * Splits a period into equal sub-periods.
	 * Divides the period into the specified number of approximately equal parts. The last part may be slightly longer if the period cannot be divided evenly. Each sub-period is returned as an array with 'start' and 'end' DateTime objects.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @param int $parts Number of equal parts to split into (must be greater than 0)
	 * @return array<int, array{start: \DateTime, end: \DateTime}> Array of sub-periods, each with 'start' and 'end' DateTime objects
	 */
	public static function split(\DateTime $periodStart, \DateTime $periodEnd, int $parts): array
	{
		if ($parts <= 0) {
			return [];
		}

		$totalDays = self::getNbDays($periodStart, $periodEnd);
		$daysPerPart = floor($totalDays / $parts);

		$periods = [];
		$current = clone $periodStart;

		for ($i = 0; $i < $parts; $i++) {
			$end = ($i === $parts - 1)
				? clone $periodEnd
				: (clone $current)->modify("+{$daysPerPart} days");

			$periods[] = ['start' => clone $current, 'end' => $end];
			$current = (clone $end)->modify('+1 day');
		}

		return $periods;
	}

	/**
	 * Checks if there is at least one weekend day (Saturday or Sunday) strictly between two dates.
	 * The boundary dates themselves are excluded from the check — only the days strictly between $from and $to are considered.
	 * Uses an early-exit optimization: any sequence of 6 or more consecutive days always contains at least one weekend day (Saturday or Sunday).
	 * @param \DateTime $from The start boundary date (excluded from the check)
	 * @param \DateTime $to The end boundary date (excluded from the check)
	 * @return bool True if at least one Saturday (ISO weekday 6) or Sunday (ISO weekday 7) falls strictly between the two dates, false otherwise
	 * @link https://www.php.net/manual/en/datetime.format.php PHP DateTime::format() — 'N' returns ISO 8601 numeric weekday (1=Monday, 7=Sunday)
	 */
	public static function hasWeekendDayBetween(\DateTime $from, \DateTime $to): bool
	{
		$fromMidnight = (clone $from)->setTime(0, 0);
		$toMidnight = (clone $to)->setTime(0, 0);

		// Number of days strictly between $from and $to (boundaries excluded)
		$nbDaysInBetween = ((int) $fromMidnight->diff($toMidnight)->format('%r%a')) - 1;

		if ($nbDaysInBetween <= 0) {
			return false;
		}

		// Any sequence of 6 or more consecutive days always includes at least one weekend day
		if ($nbDaysInBetween >= 6) {
			return true;
		}

		$current = (clone $fromMidnight)->modify('+1 day');
		while ($current < $toMidnight) {
			if ((int) $current->format('N') >= 6) {
				return true;
			}
			$current->modify('+1 day');
		}

		return false;
	}

	// ========== Timeline Layout ==========

	/**
	 * Assigns a lane index to each interval of a set of possibly overlapping time intervals, so that intervals sharing the same lane never overlap.
	 * Uses a greedy "first-fit" algorithm: intervals are processed in start-time order, and each one is placed in the first lane whose last interval already ended, or in a new lane otherwise. This does not attempt to minimize the number of lanes (unlike an optimal interval graph coloring) but is enough for simple visual layouts such as a Gantt-like timeline where overlapping items are stacked.
	 * @param array<int|string, array{start: \DateTime, end: \DateTime}> $intervals Map of intervals (same shape as returned by getOverlap()), keyed by any identifier (e.g. the source event index) so the lane assignment can be matched back to the original data
	 * @return array<int|string, int> Lane index (starting at 0) for each interval, using the same keys as $intervals
	 */
	public static function assignLanes(array $intervals): array
	{
		uasort($intervals, static fn(array $a, array $b) => $a['start'] <=> $b['start']);

		$laneEndTimes = [];
		$laneByKey = [];
		foreach ($intervals as $key => $interval) {
			$lane = null;
			foreach ($laneEndTimes as $laneIndex => $laneEndTime) {
				if ($laneEndTime <= $interval['start']) {
					$lane = $laneIndex;
					break;
				}
			}
			$lane ??= count($laneEndTimes);

			$laneEndTimes[$lane] = $interval['end'];
			$laneByKey[$key] = $lane;
		}

		return $laneByKey;
	}

	/**
	 * Counts the number of lanes used by a lane assignment returned by assignLanes().
	 * @param array<int|string, int> $laneAssignments Lane index per interval, as returned by assignLanes()
	 * @return int The number of lanes (0 if $laneAssignments is empty)
	 */
	public static function getNbLanes(array $laneAssignments): int
	{
		return empty($laneAssignments) ? 0 : (max($laneAssignments) + 1);
	}

	// ========== Labeling Methods ==========

	/**
	 * Generates a human-readable label for a date period.
	 * Creates contextual labels based on the period characteristics and language. Supports French and English locales.
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @param string $locale Language code ('fr' for French, 'en' for English). Default: 'fr'
	 * @return string A formatted label representing the period in the specified language
	 */
	public static function getLabel(\DateTime $periodStart, \DateTime $periodEnd, string $locale = 'fr'): string
	{
		return match($locale) {
			'en' => self::getLabelEn($periodStart, $periodEnd),
			'fr' => self::getLabelFr($periodStart, $periodEnd),
			default => self::getLabelFr($periodStart, $periodEnd),
		};
	}

	/**
	 * Generates a French label for a date period.
	 * Creates contextual labels: single day ("le 15 janvier 2024"), full month ("en janvier 2024"), full year ("en 2024"), or date range ("du 15/01/2024 au 20/03/2024").
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return string A formatted French label representing the period
	 */
	private static function getLabelFr(\DateTime $periodStart, \DateTime $periodEnd): string
	{
		if ($periodStart->format('Y-m-d') === $periodEnd->format('Y-m-d')) {
			return 'le '.((int) $periodStart->format('d')).' '.Date::getMonthName((int) $periodStart->format('m')).' '.$periodStart->format('Y');
		}

		$periodStartYear = (int) $periodStart->format('Y');
		$periodEndYear = (int) $periodEnd->format('Y');

		if ($periodStartYear === $periodEndYear) {
			$periodStartDay = (int) $periodStart->format('d');
			$periodEndDay = (int) $periodEnd->format('d');
			$periodStartMonth = (int) $periodStart->format('m');
			$periodEndMonth = (int) $periodEnd->format('m');

			if ($periodStartMonth === $periodEndMonth && $periodStartDay === 1 && $periodEndDay === Date::getNumberOfDaysInMonth($periodEndMonth, $periodEndYear)) {
				return 'en '.Date::getMonthName($periodStartMonth).' '.$periodStartYear;
			}
			if ($periodStartDay === 1 && $periodStartMonth === 1 && $periodEndMonth === 12 && $periodEndDay === 31) {
				return 'en '.$periodStartYear;
			}
		}

		return 'du '.DateTime::formatDate($periodStart).' au '.DateTime::formatDate($periodEnd);
	}

	/**
	 * Generates an English label for a date period.
	 * Creates contextual labels: single day ("January 15, 2024"), full month ("January 2024"), full year ("2024"), or date range ("January 15, 2024 to March 20, 2024").
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return string A formatted English label representing the period
	 */
	private static function getLabelEn(\DateTime $periodStart, \DateTime $periodEnd): string
	{
		if ($periodStart->format('Y-m-d') === $periodEnd->format('Y-m-d')) {
			return $periodStart->format('F j, Y');
		}

		$periodStartYear = (int) $periodStart->format('Y');
		$periodEndYear = (int) $periodEnd->format('Y');

		if ($periodStartYear === $periodEndYear) {
			$periodStartDay = (int) $periodStart->format('d');
			$periodEndDay = (int) $periodEnd->format('d');
			$periodStartMonth = (int) $periodStart->format('m');
			$periodEndMonth = (int) $periodEnd->format('m');

			if ($periodStartMonth === $periodEndMonth && $periodStartDay === 1 && $periodEndDay === Date::getNumberOfDaysInMonth($periodEndMonth, $periodEndYear)) {
				return $periodStart->format('F Y');
			}
			if ($periodStartDay === 1 && $periodStartMonth === 1 && $periodEndMonth === 12 && $periodEndDay === 31) {
				return (string) $periodStartYear;
			}
		}

		return $periodStart->format('F j, Y').' to '.$periodEnd->format('F j, Y');
	}

	// ========== Generic Utilities ==========

	/**
	 * Generates a list of DateTime objects for period units based on the specified period type.
	 * Returns DateTime arrays only for date-based period types (DAY_OF_MONTH, WEEK, MONTH, YEAR). Static period types (HOUR, DAY_OF_WEEK) return null - use getPeriodUnitsFormatted() instead for those types. This is useful for iterating over periods and working directly with DateTime objects.
	 * @param PeriodType $periodType The type of period to generate (DAY_OF_MONTH, WEEK, MONTH, YEAR)
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return \DateTime[]|null Array of DateTime objects for date-based types. Returns null for static types (HOUR, DAY_OF_WEEK) or unsupported types. DAY_OF_MONTH returns DateTime[], WEEK returns DateTime[] (Mondays), MONTH returns DateTime[] (1st of months), YEAR returns DateTime[] (Jan 1st of years)
	 */
	public static function getPeriodUnits(PeriodType $periodType, \DateTime $periodStart, \DateTime $periodEnd): ?array
	{
		if (PeriodType::DAY_OF_MONTH === $periodType) {
			return self::getDays($periodStart, $periodEnd);
		}
		if (PeriodType::WEEK === $periodType) {
			return self::getWeeks($periodStart, $periodEnd);
		}
		if (PeriodType::MONTH === $periodType) {
			return self::getMonths($periodStart, $periodEnd);
		}
		if (PeriodType::YEAR === $periodType) {
			return self::getYears($periodStart, $periodEnd);
		}

		return null;
	}

	/**
	 * Generates a list of formatted period unit strings based on the specified period type.
	 * Returns formatted string arrays for all period types. This is a generic utility for generating period-based lists for reports, charts, or filtering where string representation is needed.
	 * @param PeriodType $periodType The type of period to generate (HOUR, DAY_OF_MONTH, WEEK, MONTH, YEAR, DAY_OF_WEEK)
	 * @param \DateTime $periodStart The start date of the period (not used for HOUR and DAY_OF_WEEK types)
	 * @param \DateTime $periodEnd The end date of the period (not used for HOUR and DAY_OF_WEEK types)
	 * @return string[]|null Array of period units as strings, or null if the period type is not supported. Format depends on type: HOUR returns ['0'..'23'], DAY_OF_MONTH returns dates in 'Y-m-d' format, WEEK returns 'Y-W' format, MONTH returns 'Y-n' format, YEAR returns 'Y' format, DAY_OF_WEEK returns ['1'..'7']
	 */
	public static function getPeriodUnitsFormatted(PeriodType $periodType, \DateTime $periodStart, \DateTime $periodEnd): ?array
	{
		if (PeriodType::HOUR === $periodType) {
			return ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23'];
		}
		if (PeriodType::DAY_OF_MONTH === $periodType) {
			return self::getDaysFormatted($periodStart, $periodEnd, null, self::FORMAT_DATE);
		}
		if (PeriodType::WEEK === $periodType) {
			return self::getWeeksFormatted($periodStart, $periodEnd, self::FORMAT_WEEK_ISO);
		}
		if (PeriodType::MONTH === $periodType) {
			return self::getMonthsFormatted($periodStart, $periodEnd, self::FORMAT_MONTH);
		}
		if (PeriodType::YEAR === $periodType) {
			return self::getYearsFormatted($periodStart, $periodEnd, self::FORMAT_YEAR);
		}
		if (PeriodType::DAY_OF_WEEK === $periodType) {
			return ['1', '2', '3', '4', '5', '6', '7'];
		}

		return null;
	}

	// ========== DEPRECATED METHODS ==========
	// Backward compatibility. Will be removed in a future major version. Please update your code to use the new method names.

	/**
	 * @deprecated Use getDays() instead
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @param int[]|null $weekDays Optional array of weekday numbers to filter
	 * @return \DateTime[] Array of DateTime objects
	 */
	public static function getListOfDateDaysOfTheMonth(\DateTime $periodStart, \DateTime $periodEnd, ?array $weekDays=null): array
	{
		return self::getDays($periodStart, $periodEnd, $weekDays);
	}

	/**
	 * @deprecated Use getDaysFormatted() instead
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @param int[]|null $weekDays Optional array of weekday numbers to filter
	 * @param string $dateFormat PHP date format string
	 * @return string[] Array of formatted date strings
	 */
	public static function getListOfDaysOfTheMonth(\DateTime $periodStart, \DateTime $periodEnd, ?array $weekDays=null, string $dateFormat=self::FORMAT_DATE): array
	{
		return self::getDaysFormatted($periodStart, $periodEnd, $weekDays, $dateFormat);
	}

	/**
	 * @deprecated Use getWeeks() instead
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return \DateTime[] Array of DateTime objects
	 */
	public static function getListOfDateWeeks(\DateTime $periodStart, \DateTime $periodEnd): array
	{
		return self::getWeeks($periodStart, $periodEnd);
	}

	/**
	 * @deprecated Use getWeeksFormatted() instead
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @param string $dateFormat PHP date format string
	 * @return string[] Array of formatted week strings
	 */
	public static function getListOfWeeks(\DateTime $periodStart, \DateTime $periodEnd, string $dateFormat=self::FORMAT_WEEK_ISO): array
	{
		return self::getWeeksFormatted($periodStart, $periodEnd, $dateFormat);
	}

	/**
	 * @deprecated Use getMonths() instead
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return \DateTime[] Array of DateTime objects
	 */
	public static function getListOfDateMonths(\DateTime $periodStart, \DateTime $periodEnd): array
	{
		return self::getMonths($periodStart, $periodEnd);
	}

	/**
	 * @deprecated Use getMonthsFormatted() instead
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @param string $dateFormat PHP date format string
	 * @return string[] Array of formatted month strings
	 */
	public static function getListOfMonths(\DateTime $periodStart, \DateTime $periodEnd, string $dateFormat=self::FORMAT_MONTH): array
	{
		return self::getMonthsFormatted($periodStart, $periodEnd, $dateFormat);
	}

	/**
	 * @deprecated Use getDays() instead
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @param array|null $weekDays Optional array of weekday numbers to filter
	 * @return \DateTime[] Array of DateTime objects
	 */
	public static function getListDaysOfMonths(\DateTime $periodStart, \DateTime $periodEnd, ?array $weekDays=null): array
	{
		return self::getDays($periodStart, $periodEnd, $weekDays);
	}

	/**
	 * @deprecated Use getNbDays() with $withTimes=true instead
	 * @param \DateTime $startDateTime The start date and time
	 * @param \DateTime $endDateTime The end date and time
	 * @return int Number of days between dates including time component
	 */
	public static function getNbDaysBetweenDatesAndTimes(\DateTime $startDateTime, \DateTime $endDateTime): int
	{
		return self::getNbDays($startDateTime, $endDateTime, true);
	}

	/**
	 * @deprecated Use getPeriodUnits() instead
	 * @param PeriodType $periodType The type of period to generate
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return \DateTime[]|null Array of periods
	 */
	public static function getListOfPeriod(PeriodType $periodType, \DateTime $periodStart, \DateTime $periodEnd): ?array
	{
		return self::getPeriodUnits($periodType, $periodStart, $periodEnd);
	}

	/**
	 * @deprecated Use getPeriodUnitsFormatted() instead
	 * @param PeriodType $periodType The type of period to generate
	 * @param \DateTime $periodStart The start date of the period
	 * @param \DateTime $periodEnd The end date of the period
	 * @return string[]|null Array of formatted period strings
	 */
	public static function getListOfPeriodFormatted(PeriodType $periodType, \DateTime $periodStart, \DateTime $periodEnd): ?array
	{
		return self::getPeriodUnitsFormatted($periodType, $periodStart, $periodEnd);
	}
}