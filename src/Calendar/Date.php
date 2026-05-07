<?php

namespace Osimatic\Calendar;

/**
 * Date utility class providing static methods for date parsing, calculations, and formatting.
 * This class contains methods that do NOT take DateTime objects as parameters.
 * For methods that work with DateTime objects, see the DateTime class.
 *
 * Organized categories:
 * - Parsing: Parse date strings
 * - Day Names: Get localized day names
 * - Month Names: Get localized month names
 * - Calendar Info: Days in month/year, leap years, weeks in year
 * - Formatting: Format date components
 * - Validation: Validate date strings
 */
class Date
{
	// ========== Parsing Methods ==========

	/**
	 * Parses a date string in various formats and returns a DateTime object.
	 * Supports multiple input formats:
	 * - ISO 8601 with time: "YYYY-MM-DDTHH:II:SS"
	 * - Compact format with time: "YYYYMMDDHHIISS"
	 * - SQL DATE format: "YYYY-MM-DD" (time set to 00:00:00)
	 * - Various other formats supported by SqlDate::parse()
	 * @param string $str The date string to parse
	 * @return \DateTime|null A DateTime object if parsing succeeds, null otherwise
	 */
	public static function parse(string $str): ?\DateTime
	{
		if (empty($str)) {
			return null;
		}

		// Format YYYY-mm-ddTHH:ii:ss
		if (strlen($str) === strlen('YYYY-mm-ddTHH:ii:ss') && null !== ($dateTime = DateTime::parseFromSqlDateTime($str))) {
			return $dateTime;
		}

		// ISO 8601 datetime string with timezone indicator (e.g. "2024-01-15T00:00:00Z", "2024-01-15T04:00:00+05:30").
		// Extract the date part only to avoid timezone conversion shifting the calendar date
		// (e.g. UTC midnight = previous day in UTC-4 timezone such as Martinique).
		if (preg_match('/^(\d{4}-\d{2}-\d{2})T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:?\d{2})$/', $str, $matches)) {
			if (null !== ($sqlDate = SqlDate::parse($matches[1])) && false !== SqlDate::isValid($sqlDate)) {
				return DateTime::parseFromSqlDateTime($sqlDate . ' 00:00:00');
			}
		}

		// Format YYYYMMDD
		if (strlen($str) === strlen('yyyymmdd') && ctype_digit($str)) {
			$sqlDate = substr($str, 0, 4).'-'.substr($str, 4, 2).'-'.substr($str, 6, 2);
			if (null !== ($dateTime = DateTime::parseFromSqlDateTime($sqlDate.' 00:00:00'))) {
				return $dateTime;
			}
		}

		// Format YYYYmmddHHiiss
		if (strlen($str) === strlen('yyyymmddhhiiss') && ctype_digit($str)) {
			$sqlDate = substr($str, 0, 4).'-'.substr($str, 4, 2).'-'.substr($str, 6, 2);
			$sqlTime = substr($str, 8, 2).':'.substr($str, 10, 2).':'.substr($str, 12, 2);

			if (null !== ($dateTime = DateTime::parseFromSqlDateTime($sqlDate.' '.$sqlTime))) {
				return $dateTime;
			}
		}

		if (null !== ($sqlDate = SqlDate::parse($str)) && false !== SqlDate::isValid($sqlDate)) {
			return DateTime::parseFromSqlDateTime($sqlDate.' 00:00:00');
		}

		return null;
	}

	/**
	 * Alias for parse() with explicit null return.
	 * @param string $str The date string to parse
	 * @return \DateTime|null A DateTime object if parsing succeeds, null otherwise
	 */
	public static function parseOrNull(string $str): ?\DateTime
	{
		return self::parse($str);
	}

	/**
	 * Parses a date string and throws an exception if parsing fails.
	 * @param string $str The date string to parse
	 * @return \DateTime A DateTime object
	 * @throws \InvalidArgumentException If parsing fails
	 */
	public static function parseOrThrow(string $str): \DateTime
	{
		$result = self::parse($str);
		if ($result === null) {
			throw new \InvalidArgumentException("Unable to parse date string: {$str}");
		}
		return $result;
	}

	// ========== Day Names ==========

	/**
	 * Gets the localized name of a day of the week.
	 * Uses ICU IntlDateFormatter for internationalization support.
	 * Returns the full day name with the first letter capitalized.
	 * @param int $dayOfWeek ISO-8601 numeric representation of the day (1 for Monday through 7 for Sunday)
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string The localized name of the day (e.g., "Monday", "Lundi"), or empty string if dayOfWeek is invalid
	 * @see http://userguide.icu-project.org/formatparse/datetime
	 */
	public static function getDayName(int $dayOfWeek, ?string $locale=null): string
	{
		if ($dayOfWeek < 1 || $dayOfWeek > 7) {
			return '';
		}

		$timestamp = strtotime('monday this week')+(($dayOfWeek-1)*3600*24);
		return ucfirst(\IntlDateFormatter::create($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL,
			date_default_timezone_get(), \IntlDateFormatter::GREGORIAN , 'EEEE')?->format($timestamp) ?? '');
	}

	/**
	 * Gets the localized short name of a day of the week.
	 * Returns abbreviated day name (e.g., "Mon", "Tue", "Wed").
	 * @param int $dayOfWeek ISO-8601 numeric representation of the day (1 for Monday through 7 for Sunday)
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string The localized short name of the day (e.g., "Mon", "Lun"), or empty string if dayOfWeek is invalid
	 */
	public static function getDayNameShort(int $dayOfWeek, ?string $locale=null): string
	{
		if ($dayOfWeek < 1 || $dayOfWeek > 7) {
			return '';
		}

		$timestamp = strtotime('monday this week')+(($dayOfWeek-1)*3600*24);
		return ucfirst(\IntlDateFormatter::create($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL,
			date_default_timezone_get(), \IntlDateFormatter::GREGORIAN , 'EEE')?->format($timestamp) ?? '');
	}

	// ========== Month Names ==========

	/**
	 * Gets the localized name of a month.
	 * Uses ICU IntlDateFormatter for internationalization support.
	 * Returns the full month name with the first letter capitalized.
	 * @param int $month Numeric representation of a month (1-12, where 1 is January and 12 is December)
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string The localized name of the month (e.g., "January", "Janvier"), or empty string if month is invalid
	 * @see http://userguide.icu-project.org/formatparse/datetime
	 */
	public static function getMonthName(int $month, ?string $locale=null): string
	{
		if ($month < 1 || $month > 12) {
			return '';
		}

		try {
			return ucfirst(\IntlDateFormatter::create($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL,
				date_default_timezone_get(), \IntlDateFormatter::GREGORIAN, 'MMMM')?->format(new \DateTime('2020-' . sprintf('%02d', $month) . '-15 00:00:00')) ?? '');
		} catch (\Exception) {}
		return '';
	}

	/**
	 * Gets the localized short name of a month.
	 * Returns abbreviated month name (e.g., "Jan", "Feb", "Mar").
	 * @param int $month Numeric representation of a month (1-12)
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string The localized short name of the month (e.g., "Jan", "Janv"), or empty string if month is invalid
	 */
	public static function getMonthNameShort(int $month, ?string $locale=null): string
	{
		if ($month < 1 || $month > 12) {
			return '';
		}

		try {
			return ucfirst(\IntlDateFormatter::create($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL,
				date_default_timezone_get(), \IntlDateFormatter::GREGORIAN, 'MMM')?->format(new \DateTime('2020-' . sprintf('%02d', $month) . '-15 00:00:00')) ?? '');
		} catch (\Exception) {}
		return '';
	}

	/**
	 * Gets an array of all month names for a year.
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return array<int, string> Array indexed 1-12 with month names
	 */
	public static function getMonthsInYearArray(?string $locale=null): array
	{
		$months = [];
		for ($month = 1; $month <= 12; $month++) {
			$months[$month] = self::getMonthName($month, $locale);
		}
		return $months;
	}

	// ========== Calendar Info Methods ==========

	/**
	 * Gets the number of days in a specific month.
	 * Automatically handles months with different numbers of days and leap years.
	 * @param int $year The year (e.g., 2024)
	 * @param int $month The month (1-12, where 1 is January and 12 is December)
	 * @return int The number of days in the month (28-31)
	 */
	public static function getNumberOfDaysInMonth(int $year, int $month): int
	{
		return (int) date('t', mktime(0, 0, 0, $month, 1, $year));
	}

	/**
	 * Gets an array of all days in a month.
	 * @param int $year The year
	 * @param int $month The month (1-12)
	 * @return array<int, \DateTime> Array of DateTime objects for each day in the month
	 */
	public static function getDaysInMonthArray(int $year, int $month): array
	{
		$days = [];
		$numDays = self::getNumberOfDaysInMonth($year, $month);

		for ($day = 1; $day <= $numDays; $day++) {
			$dateTime = new \DateTime();
			$dateTime->setDate($year, $month, $day);
			$dateTime->setTime(0, 0, 0);
			$days[] = $dateTime;
		}

		return $days;
	}

	/**
	 * Checks if a year is a leap year.
	 * A leap year is divisible by 4, except for years divisible by 100 (unless also divisible by 400).
	 * Examples: 2020 is a leap year, 1900 is not, 2000 is a leap year.
	 * @param int $year The year to check (e.g., 2024)
	 * @return bool True if leap year, false otherwise
	 */
	public static function isLeapYear(int $year): bool
	{
		return ((($year % 4) === 0) && ((($year % 100) !== 0) || (($year % 400) === 0)));
	}

	/**
	 * Gets the number of days in a year.
	 * Returns 366 for leap years, 365 for regular years.
	 * @param int $year The year (e.g., 2024)
	 * @return int The number of days (365 or 366)
	 */
	public static function getNumberOfDaysInYear(int $year): int
	{
		return self::isLeapYear($year) ? 366 : 365;
	}

	/**
	 * Gets the number of weeks in a year according to ISO-8601.
	 * Most years have 52 weeks, but some have 53.
	 * @param int $year The year to check
	 * @return int Number of weeks in the year (52 or 53)
	 */
	public static function getWeeksInYear(int $year): int
	{
		$lastDay = new \DateTime($year . '-12-31');
		$weekNumber = (int) $lastDay->format('W');

		// If Dec 31 is in week 1 of next year, check Dec 28 (always in last week)
		if ($weekNumber === 1) {
			$lastDay = new \DateTime($year . '-12-28');
			$weekNumber = (int) $lastDay->format('W');
		}

		return $weekNumber;
	}

	/**
	 * Returns the count of each ISO-8601 day type (1=Monday … 7=Sunday) for the days remaining in the month after the reference date.
	 * "Remaining" means strictly after the reference day (the reference day itself is excluded).
	 * Useful for extrapolating a monthly total from partial data.
	 * @param \DateTimeInterface|null $referenceDate The reference date (defaults to today)
	 * @return array<int, int> Map of day-of-week type (1–7) to the number of remaining occurrences in the month
	 * @link https://en.wikipedia.org/wiki/ISO_week_date
	 */
	public static function getRemainingDayTypeCountForMonth(?\DateTimeInterface $referenceDate = null): array
	{
		$referenceDate ??= new \DateTime();
		$year  = (int) $referenceDate->format('Y');
		$month = (int) $referenceDate->format('m');
		$currentDay = (int) $referenceDate->format('d');
		$totalDays = self::getNumberOfDaysInMonth($year, $month);

		$countByDayType = [];
		for ($day = $currentDay + 1; $day <= $totalDays; $day++) {
			$dayType = (int) date('N', mktime(0, 0, 0, $month, $day, $year));
			$countByDayType[$dayType] = ($countByDayType[$dayType] ?? 0) + 1;
		}

		return $countByDayType;
	}

	// ========== Validation Methods ==========

	/**
	 * Checks if a date string can be successfully parsed.
	 * @param string $dateString The date string to validate
	 * @return bool True if valid and parseable, false otherwise
	 */
	public static function isValid(string $dateString): bool
	{
		return self::parse($dateString) !== null;
	}

	/**
	 * Validates date components (year, month, day).
	 * Checks if the given date components form a valid calendar date using PHP's checkdate() function. This includes validation of leap years and the number of days in each month.
	 * @param int $year The year (e.g., 2024)
	 * @param int $month The month (1-12)
	 * @param int $day The day (1-31, depending on month)
	 * @return bool True if the date components are valid, false otherwise
	 */
	public static function isValidDate(int $year, int $month, int $day): bool
	{
		return checkdate($month, $day, $year);
	}

}