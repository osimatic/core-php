<?php

namespace Osimatic\Calendar;

/**
 * Comprehensive utility class for DateTime manipulation, formatting, and calculations.
 * Provides 100+ static methods organized into 18 categories:
 * - Basic: Get current datetime
 * - Creation: Create DateTime objects from various sources
 * - Parsing: Parse datetime strings in various formats
 * - Formatting: Format datetime for display (IntlDateFormatter)
 * - Twig Formatting: Format for Twig templates
 * - UTC & Timezone: Convert and manipulate timezones
 * - Time Manipulation: Add/subtract hours/minutes/seconds
 * - Date Manipulation: Add/subtract days/months/years
 * - Time Rounding: Round to nearest hour/minute
 * - DateTime Comparison: Compare full datetime
 * - Date Comparison: Compare dates only (ignore time)
 * - Day of Week: Check weekends, get day names
 * - Week: Get week boundaries and numbers
 * - Month: Get month boundaries
 * - Year: Get year boundaries and age
 * - Time Calculation: Calculate differences in hours/minutes/seconds
 * - Working & Business Days: Calculate working days
 * - Deprecated: Old methods for backward compatibility
 */
class DateTime
{
	// ========== Basic Methods ==========

	/**
	 * Gets the current date and time.
	 * @return \DateTime A new DateTime object representing now
	 */
	public static function getCurrentDateTime(): \DateTime
	{
		return new \DateTime();
	}

	/**
	 * Gets the current date at midnight (00:00:00).
	 * @return \DateTime A new DateTime object for today at 00:00:00
	 */
	public static function getCurrentDate(): \DateTime
	{
		$now = new \DateTime();
		$now->setTime(0, 0, 0);
		return $now;
	}

	// ========== Creation Methods ==========

	/**
	 * Creates a DateTime object from specific date and time components.
	 * @param int $year The year (e.g., 2024)
	 * @param int $month The month (1-12)
	 * @param int $day The day (1-31)
	 * @param int $hour The hour (0-23, default: 0)
	 * @param int $minute The minute (0-59, default: 0)
	 * @param int $second The second (0-59, default: 0)
	 * @return \DateTime|null DateTime object if valid, null otherwise
	 */
	public static function create(int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0): ?\DateTime
	{
		if (!checkdate($month, $day, $year)) {
			return null;
		}

		try {
			$dateTime = new \DateTime();
			$dateTime->setDate($year, $month, $day);
			$dateTime->setTime($hour, $minute, $second);
			return $dateTime;
		} catch (\Exception) {}
		return null;
	}

	/**
	 * Creates a DateTime object from a specific date at midnight.
	 * @param int $year The year (e.g., 2024)
	 * @param int $month The month (1-12)
	 * @param int $day The day (1-31)
	 * @return \DateTime|null DateTime object if valid, null otherwise
	 */
	public static function createDate(int $year, int $month, int $day): ?\DateTime
	{
		return self::create($year, $month, $day, 0, 0, 0);
	}

	/**
	 * Creates a DateTime object from time components on today's date.
	 * Alias for Time::create() for consistency.
	 * @param int $hour The hour (0-23)
	 * @param int $minute The minute (0-59, default: 0)
	 * @param int $second The second (0-59, default: 0)
	 * @return \DateTime|null DateTime object if valid, null otherwise
	 */
	public static function createTime(int $hour, int $minute = 0, int $second = 0): ?\DateTime
	{
		return Time::create($hour, $minute, $second);
	}

	// ========== Parsing Methods ==========

	/**
	 * Parses a date string in various formats and returns a DateTime object.
	 * Delegates to Date::parse() which supports multiple formats.
	 * @param string $str The date string to parse
	 * @return \DateTime|null A DateTime object if parsing succeeds, null otherwise
	 * @see Date::parse()
	 */
	public static function parse(string $str): ?\DateTime
	{
		return Date::parse($str);
	}

	/**
	 * Parses a SQL DATETIME format string and returns a DateTime object.
	 * Accepts format: "YYYY-MM-DD HH:MM:SS"
	 * @param string $sqlDateTime SQL DATETIME format string
	 * @return \DateTime|null A DateTime object if parsing succeeds, null on error
	 */
	public static function parseFromSqlDateTime(string $sqlDateTime): ?\DateTime
	{
		try {
			return new \DateTime($sqlDateTime);
		}
		catch (\Exception) { }
		return null;
	}

	/**
	 * Creates a DateTime object from a Unix timestamp.
	 * Converts the timestamp to SQL DATETIME format before parsing.
	 * @param int $timestamp Unix timestamp (seconds since January 1, 1970)
	 * @return \DateTime|null A DateTime object if creation succeeds, null on error
	 */
	public static function parseFromTimestamp(int $timestamp): ?\DateTime
	{
		return self::parseFromSqlDateTime(date('Y-m-d H:i:s', $timestamp));
	}

	/**
	 * Creates a DateTime object from year, month, and day components.
	 * Validates the date using checkdate() before creating the DateTime.
	 * Time is set to the current time.
	 * @param int $year The year (e.g., 2024)
	 * @param int $month The month (1-12)
	 * @param int $day The day of month (1-31)
	 * @return \DateTime|null A DateTime object if date is valid, null otherwise
	 */
	public static function parseFromYearMonthDay(int $year, int $month, int $day): ?\DateTime
	{
		// Validate date components before creating DateTime
		if (!checkdate($month, $day, $year)) {
			return null;
		}

		try {
			$d = new \DateTime();
			$d->setDate($year, $month, $day);
			return $d;
		} catch (\Exception) {}
		return null;
	}

	// ========== Formatting Methods ==========

	/**
	 * Formats a DateTime using IntlDateFormatter with custom date and time format levels.
	 * Uses ICU IntlDateFormatter for internationalization support.
	 * Examples with SHORT/SHORT: en_US: "1/15/24, 2:30 PM" ; fr_FR: "15/01/2024 14:30"
	 * Examples with MEDIUM/MEDIUM: en_US: "Jan 15, 2024, 2:30:45 PM" ; fr_FR: "15 janv. 2024, 14:30:45"
	 * Examples with LONG/LONG: en_US: "January 15, 2024 at 2:30:45 PM UTC" ; fr_FR: "15 janvier 2024 à 14:30:45 UTC"
	 * Examples with FULL/FULL: en_US: "Monday, January 15, 2024 at 2:30:45 PM Coordinated Universal Time" ; fr_FR: "lundi 15 janvier 2024 à 14:30:45 temps universel coordonné"
	 * @param \DateTime $dateTime The DateTime object to format
	 * @param int $dateFormatter IntlDateFormatter constant for date format (NONE, SHORT, MEDIUM, LONG, FULL). Default: SHORT
	 * @param int $timeFormatter IntlDateFormatter constant for time format (NONE, SHORT, MEDIUM, LONG, FULL). Default: SHORT
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string The formatted date/time string, or empty string on error
	 */
	public static function format(\DateTime $dateTime, int $dateFormatter=\IntlDateFormatter::SHORT, int $timeFormatter=\IntlDateFormatter::SHORT, ?string $locale=null): string
	{
		return \IntlDateFormatter::create($locale, $dateFormatter, $timeFormatter)?->format($dateTime->getTimestamp()) ?? '';
	}

	/**
	 * Formats a DateTime with SHORT format for both date and time.
	 * Uses ICU IntlDateFormatter for internationalization.
	 * See exemples in format method.
	 * @param \DateTime $dateTime The DateTime object to format
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @param int $dateFormatter IntlDateFormatter constant for date format (NONE, SHORT, MEDIUM, LONG, FULL). Default: SHORT
	 * @param int $timeFormatter IntlDateFormatter constant for time format (NONE, SHORT, MEDIUM, LONG, FULL). Default: SHORT
	 * @return string The formatted date and time string, or empty string on error
	 */
	public static function formatDateTime(\DateTime $dateTime, ?string $locale = null, int $dateFormatter=\IntlDateFormatter::SHORT, int $timeFormatter=\IntlDateFormatter::SHORT): string
	{
		return self::format($dateTime, $dateFormatter, $timeFormatter, $locale);
	}

	// Date Formatting Methods

	/**
	 * Formats only the date portion of a DateTime.
	 * Uses ICU IntlDateFormatter for internationalization.
	 * @param \DateTime $dateTime The DateTime object to format
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @param int $dateFormatter IntlDateFormatter constant for date format (default: SHORT). Available formats:
	 *  - SHORT: Numeric date format. Exemples: en_US: "1/15/24" ; fr_FR: "15/01/2024"
	 *  - MEDIUM: Abbreviated month name with day and year. Exemples: en_US: "Jan 15, 2024" ; fr_FR: "15 janv. 2024"
	 *  - LONG: Full month name with day and year, without weekday. Exemples: en_US: "January 15, 2024" ; fr_FR: "15 janvier 2024"
	 *  - FULL: Full format with weekday. Exemples: en_US: "Monday, January 15, 2024" ; fr_FR: "lundi 15 janvier 2024"
	 * @return string The formatted date string, or empty string on error
	 */
	public static function formatDate(\DateTime $dateTime, ?string $locale=null, int $dateFormatter=\IntlDateFormatter::SHORT): string
	{
		return \IntlDateFormatter::create($locale, $dateFormatter, \IntlDateFormatter::NONE)?->format($dateTime->getTimestamp()) ?? '';
	}

	/**
	 * Formats a date in short format (numeric date).
	 * Uses ICU IntlDateFormatter for internationalization.
	 * Examples: en_US: "1/15/24" ; fr_FR: "15/01/2024"
	 * Examples with separator='-': en_US: "1-15-24" ; fr_FR: "15-01-2024"
	 * @param \DateTime $dateTime The DateTime object to format
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @param string|null $separator Optional custom separator to replace the default one (e.g., '-', '.', ' ')
	 * @return string The formatted date string, or empty string on error
	 */
	public static function formatDateShort(\DateTime $dateTime, ?string $locale = null, ?string $separator = null): string
	{
		$formatted = self::formatDate($dateTime, $locale, \IntlDateFormatter::SHORT);

		if ($separator !== null && $formatted !== '') {
			// Replace common date separators with the custom one
			$formatted = preg_replace('/[\/.\-]/', $separator, $formatted);
		}

		return $formatted;
	}

	/**
	 * Formats a date in medium format (abbreviated month name).
	 * Uses ICU IntlDateFormatter for internationalization.
	 * Examples: en_US: "Jan 15, 2024" ; fr_FR: "15 janv. 2024"
	 * @param \DateTime $dateTime The DateTime object to format
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string The formatted date string, or empty string on error
	 */
	public static function formatDateMedium(\DateTime $dateTime, ?string $locale = null): string
	{
		return self::formatDate($dateTime, $locale, \IntlDateFormatter::MEDIUM);
	}

	/**
	 * Formats a date in long format (does not include the day of the week) with localized text.
	 * Uses ICU IntlDateFormatter for internationalization.
	 * Examples: en_US: "January 15, 2024" ; fr_FR: "15 janvier 2024"
	 * @param \DateTime $dateTime The DateTime object to format
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string The formatted date string, or empty string on error
	 */
	public static function formatDateLong(\DateTime $dateTime, ?string $locale=null): string
	{
		return self::formatDate($dateTime, $locale, \IntlDateFormatter::LONG);
	}

	/**
	 * Formats a date in full format (includes the day of the week) with localized text.
	 * Uses ICU IntlDateFormatter for internationalization.
	 * Examples: en_US: "Monday, January 15, 2024" ; fr_FR: "lundi 15 janvier 2024"
	 * @param \DateTime $dateTime The DateTime object to format
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string The formatted date string, or empty string on error
	 */
	public static function formatDateFull(\DateTime $dateTime, ?string $locale=null): string
	{
		return self::formatDate($dateTime, $locale, \IntlDateFormatter::FULL);
	}

	/**
	 * Formats a date in ISO 8601 format (YYYY-MM-DD). Exemple: "2024-01-15"
	 * @param \DateTime $dateTime The date to format
	 * @return string The ISO formatted date string
	 */
	public static function formatDateISO(\DateTime $dateTime): string
	{
		return $dateTime->format('Y-m-d');
	}

	// Time Formatting Methods

	/**
	 * Formats only the time portion of a DateTime.
	 * Uses ICU IntlDateFormatter for internationalization.
	 * @param \DateTime $dateTime The DateTime object to format
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @param int $timeFormatter IntlDateFormatter constant for time format (default: SHORT). Available formats:
	 *  - SHORT: Time without seconds (HH:MM format). Examples: en_US: "2:30 PM" ; fr_FR: "14:30"
	 *  - MEDIUM: Time with seconds (HH:MM:SS format). Examples: en_US: "2:30:45 PM" ; fr_FR: "14:30:45"
	 *  - LONG: Time with seconds and timezone. Examples: en_US: "2:30:45 PM UTC" ; fr_FR: "14:30:45 UTC"
	 *  - FULL: Time with seconds and full timezone name. Examples: en_US: "2:30:45 PM Coordinated Universal Time" ; fr_FR: "14:30:45 temps universel coordonné"
	 * @return string The formatted time string, or empty string on error
	 */
	public static function formatTime(\DateTime $dateTime, ?string $locale=null, int $timeFormatter=\IntlDateFormatter::SHORT): string
	{
		return \IntlDateFormatter::create($locale, \IntlDateFormatter::NONE, $timeFormatter)?->format($dateTime->getTimestamp()) ?? '';
	}

	/**
	 * Formats a time in short HH:MM format (without seconds).
	 * Uses ICU IntlDateFormatter for internationalization.
	 * Examples: en_US: "2:30 PM" ; fr_FR: "14:30"
	 * @param \DateTime $dateTime The DateTime object to format
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string The formatted time string, or empty string on error
	 */
	public static function formatTimeShort(\DateTime $dateTime, ?string $locale=null): string
	{
		return self::formatTime($dateTime, $locale, \IntlDateFormatter::SHORT);
	}

	/**
	 * Formats a time in medium format (with seconds).
	 * Uses ICU IntlDateFormatter for internationalization.
	 * Examples: en_US: "2:30:45 PM" ; fr_FR: "14:30:45"
	 * @param \DateTime $dateTime The DateTime object to format
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string The formatted time string, or empty string on error
	 */
	public static function formatTimeMedium(\DateTime $dateTime, ?string $locale=null): string
	{
		return self::formatTime($dateTime, $locale, \IntlDateFormatter::MEDIUM);
	}

	/**
	 * Formats a time in long format (with timezone).
	 * Uses ICU IntlDateFormatter for internationalization.
	 * Examples: en_US: "2:30:45 PM UTC" ; fr_FR: "14:30:45 UTC"
	 * @param \DateTime $dateTime The DateTime object to format
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string The formatted time string, or empty string on error
	 */
	public static function formatTimeLong(\DateTime $dateTime, ?string $locale=null): string
	{
		return self::formatTime($dateTime, $locale, \IntlDateFormatter::LONG);
	}

	/**
	 * Formats a time in ISO 8601 format (HH:MM:SS). Exemple: "14:30:45"
	 * @param \DateTime $dateTime The datetime to format
	 * @return string The ISO 8601 formatted time
	 */
	public static function formatTimeISO(\DateTime $dateTime): string
	{
		return $dateTime->format('H:i:s');
	}

	// Twig Formatting Methods

	/**
	 * Formats a DateTime for use in Twig templates with string format names.
	 * Accepts both string DateTime and DateTime objects.
	 * Converts Twig format strings ('none', 'short', 'medium', 'long', 'full') to IntlDateFormatter constants.
	 * @param string|\DateTime|null $dateTime The DateTime to format (string or object)
	 * @param string $dateFormatter Format name: 'none', 'short', 'medium', 'long', 'full' (default: 'short')
	 * @param string $timeFormatter Format name: 'none', 'short', 'medium', 'long', 'full' (default: 'short')
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string|null The formatted date/time string, or null if input is null
	 * @throws \Exception If string datetime cannot be parsed
	 */
	public static function formatFromTwig(string|\DateTime|null $dateTime, string $dateFormatter='short', string $timeFormatter='short', ?string $locale=null): ?string
	{
		if (null === $dateTime) {
			return null;
		}

		if (is_string($dateTime)) {
			$dateTime = new \DateTime($dateTime);
		}

		return self::format($dateTime, self::getDateTimeFormatterFromTwig($dateFormatter), self::getDateTimeFormatterFromTwig($timeFormatter), $locale);
	}

	/**
	 * Formats only the date portion for use in Twig templates with string format name.
	 * Converts Twig format string ('none', 'short', 'medium', 'long', 'full') to IntlDateFormatter constant.
	 * @param \DateTime|null $dateTime The DateTime to format
	 * @param string $dateFormatter Format name: 'none', 'short', 'medium', 'long', 'full' (default: 'short')
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string|null The formatted date string, or null if input is null
	 */
	public static function formatDateFromTwig(?\DateTime $dateTime, string $dateFormatter='short', ?string $locale=null): ?string
	{
		if (null === $dateTime) {
			return null;
		}

		return self::format($dateTime, self::getDateTimeFormatterFromTwig($dateFormatter), \IntlDateFormatter::NONE, $locale);
	}

	/**
	 * Formats only the time portion for use in Twig templates with string format name.
	 * Converts Twig format string ('none', 'short', 'medium', 'long', 'full') to IntlDateFormatter constant.
	 * @param \DateTime|null $dateTime The DateTime to format
	 * @param string $timeFormatter Format name: 'none', 'short', 'medium', 'long', 'full' (default: 'short')
	 * @param string|null $locale Optional locale code (e.g., 'en_US', 'fr_FR'). Uses default if null
	 * @return string|null The formatted time string, or null if input is null
	 */
	public static function formatTimeFromTwig(?\DateTime $dateTime, string $timeFormatter='short', ?string $locale=null): ?string
	{
		if (null === $dateTime) {
			return null;
		}

		return self::format($dateTime, \IntlDateFormatter::NONE, self::getDateTimeFormatterFromTwig($timeFormatter), $locale);
	}

	/**
	 * Converts Twig format string to IntlDateFormatter constant.
	 * Maps string format names to their corresponding IntlDateFormatter integer constants.
	 * @param string $formatter Format name: 'none', 'full', 'long', 'medium', 'short'
	 * @return int IntlDateFormatter constant
	 */
	private static function getDateTimeFormatterFromTwig(string $formatter): int
	{
		return match ($formatter) {
			'none' => \IntlDateFormatter::NONE,
			'full' => \IntlDateFormatter::FULL,
			'long' => \IntlDateFormatter::LONG,
			'medium' => \IntlDateFormatter::MEDIUM,
			default => \IntlDateFormatter::SHORT,
		};
	}

	// ========== UTC & Timezone Methods ==========

	/**
	 * Converts a DateTime to UTC timezone and returns SQL DATE format.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime|null $dateTime The DateTime to convert
	 * @return string|null SQL DATE format string (YYYY-MM-DD) in UTC, or null if input is null
	 */
	public static function getUTCSqlDate(?\DateTime $dateTime): ?string
	{
		return null !== $dateTime ? (clone $dateTime)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d') : null;
	}

	/**
	 * Converts a DateTime to UTC timezone and returns SQL TIME format.
	 * Creates a clone to avoid modifying the original DateTime.
	 * If the DateTime has the Unix epoch date (1970-01-01), the date is normalized to today
	 * before conversion to ensure correct DST offset (DST rules in 1970 may differ from today).
	 * @param \DateTime|null $dateTime The DateTime to convert
	 * @return string|null SQL TIME format string (HH:MM:SS) in UTC, or null if input is null
	 */
	public static function getUTCSqlTime(?\DateTime $dateTime): ?string
	{
		if (null === $dateTime) {
			return null;
		}
		$dt = clone $dateTime;
		if ($dt->format('Y-m-d') === '1970-01-01') {
			$dt->setDate((int) date('Y'), (int) date('m'), (int) date('d'));
		}
		return $dt->setTimezone(new \DateTimeZone('UTC'))->format('H:i:s');
	}

	/**
	 * Converts a DateTime to UTC timezone and returns SQL DATETIME format.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime|null $dateTime The DateTime to convert
	 * @return string|null SQL DATETIME format string (YYYY-MM-DD HH:MM:SS) in UTC, or null if input is null
	 */
	public static function getUTCSqlDateTime(?\DateTime $dateTime): ?string
	{
		return null !== $dateTime ? (clone $dateTime)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') : null;
	}

	/**
	 * Converts a DateTime to a specific timezone.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The DateTime to convert
	 * @param string $timezone Timezone name (e.g., 'America/New_York', 'Europe/Paris')
	 * @return \DateTime DateTime object in the specified timezone
	 */
	public static function convertToTimezone(\DateTime $dateTime, string $timezone): \DateTime
	{
		$result = clone $dateTime;
		$result->setTimezone(new \DateTimeZone($timezone));
		return $result;
	}

	/**
	 * Converts a DateTime to UTC timezone.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The DateTime to convert
	 * @return \DateTime DateTime object in UTC timezone
	 */
	public static function convertToUTC(\DateTime $dateTime): \DateTime
	{
		return self::convertToTimezone($dateTime, 'UTC');
	}

	/**
	 * Gets the timezone name of a DateTime.
	 * @param \DateTime $dateTime The DateTime to check
	 * @return string Timezone name (e.g., 'UTC', 'Europe/Paris')
	 */
	public static function getTimezoneName(\DateTime $dateTime): string
	{
		return $dateTime->getTimezone()->getName();
	}

	/**
	 * Gets the timezone offset in seconds from UTC.
	 * @param \DateTime $dateTime The DateTime to check
	 * @return int Offset in seconds (positive = east of UTC, negative = west of UTC)
	 */
	public static function getTimezoneOffset(\DateTime $dateTime): int
	{
		return $dateTime->getOffset();
	}

	// ========== Date Manipulation Methods ==========

	/**
	 * Adds days to a DateTime.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The reference datetime
	 * @param int $days Number of days to add (can be negative)
	 * @return \DateTime New DateTime with days added
	 */
	public static function addDays(\DateTime $dateTime, int $days): \DateTime
	{
		$result = clone $dateTime;
		$result->modify($days >= 0 ? "+{$days} days" : "{$days} days");
		return $result;
	}

	/**
	 * Subtracts days from a DateTime.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The reference datetime
	 * @param int $days Number of days to subtract
	 * @return \DateTime New DateTime with days subtracted
	 */
	public static function subDays(\DateTime $dateTime, int $days): \DateTime
	{
		return self::addDays($dateTime, -$days);
	}

	/**
	 * Moves a DateTime backward by a specified number of days.
	 * Creates a new DateTime object to avoid modifying the original.
	 * @param \DateTime $dateTime The datetime to move
	 * @param int $nbDays Number of days to move backward
	 * @return \DateTime A new DateTime moved backward by the specified days
	 */
	public static function moveBackOfNbDays(\DateTime $dateTime, int $nbDays): \DateTime
	{
		return self::subDays($dateTime, $nbDays);
	}

	/**
	 * Moves a DateTime forward by a specified number of days.
	 * Creates a new DateTime object to avoid modifying the original.
	 * @param \DateTime $dateTime The datetime to move
	 * @param int $nbDays Number of days to move forward
	 * @return \DateTime A new DateTime moved forward by the specified days
	 */
	public static function moveForwardOfNbDays(\DateTime $dateTime, int $nbDays): \DateTime
	{
		return self::addDays($dateTime, $nbDays);
	}

	/**
	 * Adds months to a DateTime.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The reference datetime
	 * @param int $months Number of months to add (can be negative)
	 * @return \DateTime New DateTime with months added
	 */
	public static function addMonths(\DateTime $dateTime, int $months): \DateTime
	{
		$result = clone $dateTime;
		$result->modify($months >= 0 ? "+{$months} months" : "{$months} months");
		return $result;
	}

	/**
	 * Subtracts months from a DateTime.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The reference datetime
	 * @param int $months Number of months to subtract
	 * @return \DateTime New DateTime with months subtracted
	 */
	public static function subMonths(\DateTime $dateTime, int $months): \DateTime
	{
		return self::addMonths($dateTime, -$months);
	}

	/**
	 * Moves a DateTime backward by a specified number of months.
	 * @param \DateTime $dateTime The datetime to move
	 * @param int $nbMonths Number of months to move backward
	 * @return \DateTime A new DateTime moved backward by the specified months
	 */
	public static function moveBackOfNbMonths(\DateTime $dateTime, int $nbMonths): \DateTime
	{
		return self::subMonths($dateTime, $nbMonths);
	}

	/**
	 * Moves a DateTime forward by a specified number of months.
	 * @param \DateTime $dateTime The datetime to move
	 * @param int $nbMonths Number of months to move forward
	 * @return \DateTime A new DateTime moved forward by the specified months
	 */
	public static function moveForwardOfNbMonths(\DateTime $dateTime, int $nbMonths): \DateTime
	{
		return self::addMonths($dateTime, $nbMonths);
	}

	/**
	 * Adds years to a DateTime.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The reference datetime
	 * @param int $years Number of years to add (can be negative)
	 * @return \DateTime New DateTime with years added
	 */
	public static function addYears(\DateTime $dateTime, int $years): \DateTime
	{
		$result = clone $dateTime;
		$result->modify($years >= 0 ? "+{$years} years" : "{$years} years");
		return $result;
	}

	/**
	 * Subtracts years from a DateTime.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The reference datetime
	 * @param int $years Number of years to subtract
	 * @return \DateTime New DateTime with years subtracted
	 */
	public static function subYears(\DateTime $dateTime, int $years): \DateTime
	{
		return self::addYears($dateTime, -$years);
	}

	// ========== Time Manipulation Methods ==========

	/**
	 * Adds hours to a DateTime.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The reference datetime
	 * @param int $hours Number of hours to add (can be negative)
	 * @return \DateTime New DateTime with hours added
	 */
	public static function addHours(\DateTime $dateTime, int $hours): \DateTime
	{
		$result = clone $dateTime;
		$result->modify($hours >= 0 ? "+{$hours} hours" : "{$hours} hours");
		return $result;
	}

	/**
	 * Subtracts hours from a DateTime.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The reference datetime
	 * @param int $hours Number of hours to subtract
	 * @return \DateTime New DateTime with hours subtracted
	 */
	public static function subHours(\DateTime $dateTime, int $hours): \DateTime
	{
		return self::addHours($dateTime, -$hours);
	}

	/**
	 * Adds minutes to a DateTime.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The reference datetime
	 * @param int $minutes Number of minutes to add (can be negative)
	 * @return \DateTime New DateTime with minutes added
	 */
	public static function addMinutes(\DateTime $dateTime, int $minutes): \DateTime
	{
		$result = clone $dateTime;
		$result->modify($minutes >= 0 ? "+{$minutes} minutes" : "{$minutes} minutes");
		return $result;
	}

	/**
	 * Subtracts minutes from a DateTime.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The reference datetime
	 * @param int $minutes Number of minutes to subtract
	 * @return \DateTime New DateTime with minutes subtracted
	 */
	public static function subMinutes(\DateTime $dateTime, int $minutes): \DateTime
	{
		return self::addMinutes($dateTime, -$minutes);
	}

	/**
	 * Adds seconds to a DateTime.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The reference datetime
	 * @param int $seconds Number of seconds to add (can be negative)
	 * @return \DateTime New DateTime with seconds added
	 */
	public static function addSeconds(\DateTime $dateTime, int $seconds): \DateTime
	{
		$result = clone $dateTime;
		$result->modify($seconds >= 0 ? "+{$seconds} seconds" : "{$seconds} seconds");
		return $result;
	}

	/**
	 * Subtracts seconds from a DateTime.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The reference datetime
	 * @param int $seconds Number of seconds to subtract
	 * @return \DateTime New DateTime with seconds subtracted
	 */
	public static function subSeconds(\DateTime $dateTime, int $seconds): \DateTime
	{
		return self::addSeconds($dateTime, -$seconds);
	}

	// ========== Time Rounding Methods ==========

	/**
	 * Rounds a DateTime down to the start of the hour (XX:00:00).
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The datetime to round
	 * @return \DateTime New DateTime rounded down to the hour
	 */
	public static function floorToHour(\DateTime $dateTime): \DateTime
	{
		$result = clone $dateTime;
		$result->setTime((int) $result->format('H'), 0, 0);
		return $result;
	}

	/**
	 * Rounds a DateTime up to the start of the next hour (XX:00:00).
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The datetime to round
	 * @return \DateTime New DateTime rounded up to the next hour
	 */
	public static function ceilToHour(\DateTime $dateTime): \DateTime
	{
		$result = clone $dateTime;
		$minute = (int) $result->format('i');
		$second = (int) $result->format('s');

		if ($minute > 0 || $second > 0) {
			$result->setTime((int) $result->format('H'), 0, 0);
			$result->modify('+1 hour');
		} else {
			$result->setTime((int) $result->format('H'), 0, 0);
		}

		return $result;
	}

	/**
	 * Rounds a DateTime to the nearest hour.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The datetime to round
	 * @return \DateTime New DateTime rounded to nearest hour
	 */
	public static function roundToHour(\DateTime $dateTime): \DateTime
	{
		$minute = (int) $dateTime->format('i');

		if ($minute >= 30) {
			return self::ceilToHour($dateTime);
		}

		return self::floorToHour($dateTime);
	}

	/**
	 * Rounds a DateTime down to a specific minute interval (XX:YY:00).
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The datetime to round
	 * @param int $minutes Minute interval (e.g., 15 for quarter-hour)
	 * @return \DateTime New DateTime rounded down
	 */
	public static function floorToMinutes(\DateTime $dateTime, int $minutes = 1): \DateTime
	{
		if ($minutes <= 0) {
			$minutes = 1;
		}

		$result = clone $dateTime;
		$currentMinute = (int) $result->format('i');
		$flooredMinute = (int) (floor($currentMinute / $minutes) * $minutes);
		$result->setTime((int) $result->format('H'), $flooredMinute, 0);

		return $result;
	}

	/**
	 * Rounds a DateTime up to the next minute interval.
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The datetime to round
	 * @param int $minutes Minute interval (e.g., 15 for quarter-hour)
	 * @return \DateTime New DateTime rounded up
	 */
	public static function ceilToMinutes(\DateTime $dateTime, int $minutes = 1): \DateTime
	{
		if ($minutes <= 0) {
			$minutes = 1;
		}

		$result = clone $dateTime;
		$currentMinute = (int) $result->format('i');
		$currentSecond = (int) $result->format('s');
		$flooredMinute = (int) (floor($currentMinute / $minutes) * $minutes);

		if ($currentMinute > $flooredMinute || $currentSecond > 0) {
			$ceiledMinute = $flooredMinute + $minutes;
			$hour = (int) $result->format('H');

			if ($ceiledMinute >= 60) {
				$hour++;
				$ceiledMinute = 0;
			}

			$result->setTime($hour, $ceiledMinute, 0);
		} else {
			$result->setTime((int) $result->format('H'), $flooredMinute, 0);
		}

		return $result;
	}

	// ========== DateTime Comparison Methods ==========

	/**
	 * Checks if a DateTime is in the past (including both date and time).
	 * Compares full datetime including hours, minutes, and seconds.
	 * @param \DateTime $dateTime The DateTime to check
	 * @return bool True if the datetime is before now
	 */
	public static function isInThePast(\DateTime $dateTime): bool
	{
		return $dateTime < self::getCurrentDateTime();
	}

	/**
	 * Checks if a DateTime is in the future (including both date and time).
	 * Compares full datetime including hours, minutes, and seconds.
	 * @param \DateTime $dateTime The DateTime to check
	 * @return bool True if the datetime is after now
	 */
	public static function isInTheFuture(\DateTime $dateTime): bool
	{
		return $dateTime > self::getCurrentDateTime();
	}

	/**
	 * Checks if two DateTimes are exactly the same (including time).
	 * @param \DateTime $dateTime1 First datetime
	 * @param \DateTime $dateTime2 Second datetime
	 * @return bool True if same datetime
	 */
	public static function isSameDateTime(\DateTime $dateTime1, \DateTime $dateTime2): bool
	{
		return $dateTime1->format('Y-m-d H:i:s') === $dateTime2->format('Y-m-d H:i:s');
	}

	/**
	 * Checks if a DateTime is between two other DateTimes (inclusive).
	 * @param \DateTime $dateTime The datetime to check
	 * @param \DateTime $startDateTime Start of range
	 * @param \DateTime $endDateTime End of range
	 * @param bool $inclusive Whether to include boundaries (default: true)
	 * @return bool True if between startDateTime and endDateTime
	 */
	public static function isBetweenDateTimes(\DateTime $dateTime, \DateTime $startDateTime, \DateTime $endDateTime, bool $inclusive = true): bool
	{
		if ($inclusive) {
			return $dateTime >= $startDateTime && $dateTime <= $endDateTime;
		}
		return $dateTime > $startDateTime && $dateTime < $endDateTime;
	}

	// ========== Date Comparison Methods ==========

	/**
	 * Checks if the first date is after the second date (ignoring time).
	 * Only compares the date portion (year, month, day), not the time.
	 * @param \DateTime $dateTime1 First datetime to compare
	 * @param \DateTime $dateTime2 Second datetime to compare
	 * @return bool True if dateTime1's date is after dateTime2's date
	 */
	public static function isDateAfter(\DateTime $dateTime1, \DateTime $dateTime2): bool
	{
		return $dateTime1->format('Ymd') > $dateTime2->format('Ymd');
	}

	/**
	 * Checks if the first date is before the second date (ignoring time).
	 * Only compares the date portion (year, month, day), not the time.
	 * @param \DateTime $dateTime1 First datetime to compare
	 * @param \DateTime $dateTime2 Second datetime to compare
	 * @return bool True if dateTime1's date is before dateTime2's date
	 */
	public static function isDateBefore(\DateTime $dateTime1, \DateTime $dateTime2): bool
	{
		return $dateTime1->format('Ymd') < $dateTime2->format('Ymd');
	}

	/**
	 * Checks if a date is in the past (ignoring time).
	 * Only compares the date portion (year, month, day).
	 * @param \DateTime $dateTime The DateTime to check
	 * @return bool True if the date (not time) is before today
	 */
	public static function isDateInThePast(\DateTime $dateTime): bool
	{
		return $dateTime->format('Ymd') < self::getCurrentDateTime()->format('Ymd');
	}

	/**
	 * Checks if a date is in the future (ignoring time).
	 * Only compares the date portion (year, month, day).
	 * @param \DateTime $dateTime The DateTime to check
	 * @return bool True if the date (not time) is after today
	 */
	public static function isDateInTheFuture(\DateTime $dateTime): bool
	{
		return $dateTime->format('Ymd') > self::getCurrentDateTime()->format('Ymd');
	}

	/**
	 * Checks if two DateTimes are on the same day (ignoring time).
	 * Compares only the date portion (year, month, day).
	 * @param \DateTime $dateTime1 First datetime
	 * @param \DateTime $dateTime2 Second datetime
	 * @return bool True if same day
	 */
	public static function isSameDay(\DateTime $dateTime1, \DateTime $dateTime2): bool
	{
		return $dateTime1->format('Y-m-d') === $dateTime2->format('Y-m-d');
	}

	/**
	 * Checks if a DateTime is today (ignoring time).
	 * Compares only the date portion (year, month, day).
	 * @param \DateTime $dateTime The datetime to check
	 * @return bool True if today
	 */
	public static function isToday(\DateTime $dateTime): bool
	{
		return self::isSameDay($dateTime, self::getCurrentDateTime());
	}

	// ========== Time Comparison Methods ==========

	/**
	 * Checks if two DateTimes have the same time (ignoring date).
	 * Compares only the time portion (hours, minutes, seconds).
	 * @param \DateTime $dateTime1 First datetime
	 * @param \DateTime $dateTime2 Second datetime
	 * @return bool True if same time
	 */
	public static function isSameTime(\DateTime $dateTime1, \DateTime $dateTime2): bool
	{
		return $dateTime1->format('H:i:s') === $dateTime2->format('H:i:s');
	}

	/**
	 * Checks if two DateTimes have the same hour (ignoring date and minutes).
	 * @param \DateTime $dateTime1 First datetime
	 * @param \DateTime $dateTime2 Second datetime
	 * @return bool True if same hour
	 */
	public static function isSameHour(\DateTime $dateTime1, \DateTime $dateTime2): bool
	{
		return $dateTime1->format('Y-m-d H') === $dateTime2->format('Y-m-d H');
	}

	// ========== Day of Week Methods ==========

	/**
	 * Checks if a date is a weekend day (Saturday or Sunday).
	 * @param \DateTime $dateTime The date to check
	 * @return bool True if the date is Saturday or Sunday
	 */
	public static function isWeekend(\DateTime $dateTime): bool
	{
		$dayOfWeek = (int) $dateTime->format('N');
		return $dayOfWeek === 6 || $dayOfWeek === 7;
	}

	/**
	 * Checks if a date falls on a weekday (Monday through Friday).
	 * @param \DateTime $dateTime The date to check
	 * @return bool True if Monday through Friday, false otherwise
	 */
	public static function isWeekday(\DateTime $dateTime): bool
	{
		return !self::isWeekend($dateTime);
	}

	/**
	 * Gets the ISO-8601 numeric representation of the day of the week.
	 * @param \DateTime $dateTime The date to check
	 * @return int Day of week (1 for Monday through 7 for Sunday)
	 */
	public static function getDayOfWeek(\DateTime $dateTime): int
	{
		return (int) $dateTime->format('N');
	}

	/**
	 * Finds the next occurrence of a specific weekday from a DateTime.
	 * @param \DateTime $dateTime The starting datetime
	 * @param int $weekDay ISO-8601 day of week (1=Monday to 7=Sunday)
	 * @return \DateTime DateTime of the next occurrence
	 */
	public static function getNextWeekDay(\DateTime $dateTime, int $weekDay): \DateTime
	{
		$result = clone $dateTime;
		while (((int) $result->format('N')) !== $weekDay) {
			$result->modify('+1 day');
		}
		return $result;
	}

	/**
	 * Finds the previous occurrence of a specific weekday from a DateTime.
	 * @param \DateTime $dateTime The starting datetime
	 * @param int $weekDay ISO-8601 day of week (1=Monday to 7=Sunday)
	 * @return \DateTime DateTime of the previous occurrence
	 */
	public static function getPreviousWeekDay(\DateTime $dateTime, int $weekDay): \DateTime
	{
		$result = clone $dateTime;
		while (((int) $result->format('N')) !== $weekDay) {
			$result->modify('-1 day');
		}
		return $result;
	}

	// ========== Week Methods ==========

	/**
	 * Gets the ISO-8601 week number and year for a DateTime.
	 * Handles edge case where week 1 is in December (returns next year).
	 * @param \DateTime $dateTime The datetime to check
	 * @return array Array with [year, weekNumber]
	 */
	public static function getWeekNumber(\DateTime $dateTime): array
	{
		$weekNumber = $dateTime->format('W');
		$year = $dateTime->format('Y');
		// If week 1 and month is December, increment year
		if (((int)$weekNumber) === 1 && ((int)$dateTime->format('m')) === 12) {
			$year++;
		}
		return [$year, $weekNumber];
	}

	/**
	 * Gets the first day of the current week (Monday).
	 * @return \DateTime|null DateTime for Monday of current week at 00:00:00
	 */
	public static function getFirstDayOfCurrentWeek(): ?\DateTime
	{
		return self::parseFromSqlDateTime(SqlDate::getFirstDayOfWeek((int) date('Y'), (int) date('W')).' 00:00:00');
	}

	/**
	 * Gets the last day of the current week (Sunday).
	 * @return \DateTime|null DateTime for Sunday of current week at 00:00:00
	 */
	public static function getLastDayOfCurrentWeek(): ?\DateTime
	{
		return self::parseFromSqlDateTime(SqlDate::getLastDayOfWeek((int) date('Y'), (int) date('W')).' 00:00:00');
	}

	/**
	 * Gets the first day of the previous week (Monday).
	 * @return \DateTime|null DateTime for Monday of previous week at 00:00:00
	 */
	public static function getFirstDayOfPreviousWeek(): ?\DateTime
	{
		return self::parseFromSqlDateTime(date('Y-m-d', strtotime('monday last week')).' 00:00:00');
	}

	/**
	 * Gets the last day of the previous week (Sunday).
	 * @return \DateTime|null DateTime for Sunday of previous week at 00:00:00
	 */
	public static function getLastDayOfPreviousWeek(): ?\DateTime
	{
		return self::parseFromSqlDateTime(date('Y-m-d', strtotime('sunday last week')).' 00:00:00');
	}

	/**
	 * Gets the first day of the next week (Monday).
	 * @return \DateTime|null DateTime for Monday of next week at 00:00:00
	 */
	public static function getFirstDayOfNextWeek(): ?\DateTime
	{
		return self::parseFromSqlDateTime(date('Y-m-d', strtotime('monday next week')).' 00:00:00');
	}

	/**
	 * Gets the last day of the next week (Sunday).
	 * @return \DateTime|null DateTime for Sunday of next week at 00:00:00
	 */
	public static function getLastDayOfNextWeek(): ?\DateTime
	{
		return self::parseFromSqlDateTime(date('Y-m-d', strtotime('sunday next week')).' 00:00:00');
	}

	/**
	 * Gets the first day (Monday) of a specific week.
	 * @param int $year The year
	 * @param int $week The ISO-8601 week number (1-53)
	 * @return \DateTime|null DateTime for Monday of the specified week at 00:00:00
	 */
	public static function getFirstDayOfWeek(int $year, int $week): ?\DateTime
	{
		return self::parseFromSqlDateTime(SqlDate::getFirstDayOfWeek($year, $week).' 00:00:00');
	}

	/**
	 * Gets the last day (Sunday) of a specific week.
	 * @param int $year The year
	 * @param int $week The ISO-8601 week number (1-53)
	 * @return \DateTime|null DateTime for Sunday of the specified week at 00:00:00
	 */
	public static function getLastDayOfWeek(int $year, int $week): ?\DateTime
	{
		return self::parseFromSqlDateTime(SqlDate::getLastDayOfWeek($year, $week).' 00:00:00');
	}

	/**
	 * Gets the first day of the week containing a specific date.
	 * @param \DateTime $dateTime The reference date
	 * @return \DateTime|null DateTime for Monday of the week containing the date
	 */
	public static function getFirstDayOfWeekOfDate(\DateTime $dateTime): ?\DateTime
	{
		return self::getFirstDayOfWeek((int) $dateTime->format('Y'), (int) $dateTime->format('W'));
	}

	/**
	 * Gets the last day of the week containing a specific date.
	 * @param \DateTime $dateTime The reference date
	 * @return \DateTime|null DateTime for Sunday of the week containing the date
	 */
	public static function getLastDayOfWeekOfDate(\DateTime $dateTime): ?\DateTime
	{
		return self::getLastDayOfWeek((int) $dateTime->format('Y'), (int) $dateTime->format('W'));
	}

	// ========== Month Methods ==========

	/**
	 * Gets the first day of the current month.
	 * @return \DateTime|null DateTime for the 1st of current month at 00:00:00
	 */
	public static function getFirstDayOfCurrentMonth(): ?\DateTime
	{
		return self::parseFromSqlDateTime(SqlDate::getFirstDayOfMonth((int) date('Y'), (int) date('m')).' 00:00:00');
	}

	/**
	 * Gets the last day of the current month.
	 * @return \DateTime|null DateTime for the last day of current month at 00:00:00
	 */
	public static function getLastDayOfCurrentMonth(): ?\DateTime
	{
		return self::parseFromSqlDateTime(SqlDate::getLastDayOfMonth((int) date('Y'), (int) date('m')).' 00:00:00');
	}

	/**
	 * Gets the first day of the previous month.
	 * @return \DateTime|null DateTime for the 1st of previous month at 00:00:00
	 */
	public static function getFirstDayOfPreviousMonth(): ?\DateTime
	{
		return self::parseFromSqlDateTime(date('Y-m-d', strtotime('first day of previous month')).' 00:00:00');
	}

	/**
	 * Gets the last day of the previous month.
	 * @return \DateTime|null DateTime for the last day of previous month at 00:00:00
	 */
	public static function getLastDayOfPreviousMonth(): ?\DateTime
	{
		return self::parseFromSqlDateTime(date('Y-m-d', strtotime('last day of previous month')).' 00:00:00');
	}

	/**
	 * Gets the first day of the next month.
	 * @return \DateTime|null DateTime for the 1st of next month at 00:00:00
	 */
	public static function getFirstDayOfNextMonth(): ?\DateTime
	{
		return self::parseFromSqlDateTime(date('Y-m-d', strtotime('first day of next month')).' 00:00:00');
	}

	/**
	 * Gets the last day of the next month.
	 * @return \DateTime|null DateTime for the last day of next month at 00:00:00
	 */
	public static function getLastDayOfNextMonth(): ?\DateTime
	{
		return self::parseFromSqlDateTime(date('Y-m-d', strtotime('last day of next month')).' 00:00:00');
	}

	/**
	 * Gets the first day of a specific month.
	 * @param int $year The year
	 * @param int $month The month (1-12)
	 * @return \DateTime|null DateTime for the 1st of the specified month at 00:00:00
	 */
	public static function getFirstDayOfMonth(int $year, int $month): ?\DateTime
	{
		return self::parseFromSqlDateTime(SqlDate::getFirstDayOfMonth($year, $month).' 00:00:00');
	}

	/**
	 * Gets the last day of a specific month.
	 * @param int $year The year
	 * @param int $month The month (1-12)
	 * @return \DateTime|null DateTime for the last day of the specified month at 00:00:00
	 */
	public static function getLastDayOfMonth(int $year, int $month): ?\DateTime
	{
		return self::parseFromSqlDateTime(SqlDate::getLastDayOfMonth($year, $month).' 00:00:00');
	}

	/**
	 * Gets the first day of the month containing a specific date.
	 * @param \DateTime $dateTime The reference date
	 * @return \DateTime|null DateTime for the 1st of the month at 00:00:00
	 */
	public static function getFirstDayOfMonthOfDate(\DateTime $dateTime): ?\DateTime
	{
		return self::getFirstDayOfMonth((int) $dateTime->format('Y'), (int) $dateTime->format('m'));
	}

	/**
	 * Gets the last day of the month containing a specific date.
	 * @param \DateTime $dateTime The reference date
	 * @return \DateTime|null DateTime for the last day of the month at 00:00:00
	 */
	public static function getLastDayOfMonthOfDate(\DateTime $dateTime): ?\DateTime
	{
		return self::getLastDayOfMonth((int) $dateTime->format('Y'), (int) $dateTime->format('m'));
	}

	/**
	 * Gets the n-th occurrence of a weekday in a specific month.
	 * Example: "2nd Wednesday of the month"
	 * @param int $year The year
	 * @param int $month The month (1-12)
	 * @param int $weekDay ISO-8601 day of week (1=Monday to 7=Sunday)
	 * @param int $number Occurrence number (1-5)
	 * @return \DateTime|null DateTime for the n-th weekday, or null if doesn't exist
	 */
	public static function getWeekDayOfMonth(int $year, int $month, int $weekDay, int $number): ?\DateTime
	{
		$weekDayName = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'][$weekDay-1] ?? null;
		if (null === $weekDayName) {
			return null;
		}

		$numberName = ['first', 'second', 'third', 'fourth', 'fifth'][$number-1] ?? null;
		if (null === $numberName) {
			return null;
		}

		try {
			$dateTime = new \DateTime($year.'-'.$month.'-01 00:00:00');
			$dateTime->modify($numberName.' '.$weekDayName.' of this month');

			if (((int) $dateTime->format('Y')) !== $year || ((int) $dateTime->format('m')) !== $month) {
				return null;
			}

			return $dateTime;
		}
		catch (\Exception) {}
		return null;
	}

	/**
	 * Gets the last occurrence of a weekday in a specific month.
	 * Example: "Last Wednesday of the month"
	 * @param int $year The year
	 * @param int $month The month (1-12)
	 * @param int $weekDay ISO-8601 day of week (1=Monday to 7=Sunday)
	 * @return \DateTime|null DateTime for the last weekday occurrence
	 */
	public static function getLastWeekDayOfMonth(int $year, int $month, int $weekDay): ?\DateTime
	{
		$weekDayName = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'][$weekDay-1] ?? null;
		if (null === $weekDayName) {
			return null;
		}
		try {
			$dateTime = new \DateTime($year.'-'.$month.'-01 00:00:00');
			$dateTime->modify('+1 month');
			$dateTime->modify('last '.$weekDayName);
			return $dateTime;
		}
		catch (\Exception) {}
		return null;
	}

	// ========== Year Methods ==========

	/**
	 * Calculates the age in years from a birthdate to now.
	 * @param \DateTime $birthDate The birth date
	 * @return int Age in years
	 */
	public static function calculateAge(\DateTime $birthDate): int
	{
		$now = self::getCurrentDateTime();
		$diff = $now->diff($birthDate);
		return (int) $diff->y;
	}

	/**
	 * Gets the age in years from a birthdate to now.
	 * Alias for calculateAge() with clearer naming.
	 * @param \DateTime $birthDate The birth date
	 * @return int Age in years
	 */
	public static function getAge(\DateTime $birthDate): int
	{
		return self::calculateAge($birthDate);
	}

	// ========== Time Calculation Methods ==========

	/**
	 * Calculates the number of hours between two DateTimes.
	 * @param \DateTime $startDateTime Start datetime
	 * @param \DateTime $endDateTime End datetime
	 * @param bool $absolute Whether to return absolute value (default: true)
	 * @return float Number of hours between datetimes (can include decimals)
	 */
	public static function getHoursBetween(\DateTime $startDateTime, \DateTime $endDateTime, bool $absolute = true): float
	{
		$seconds = self::getSecondsBetween($startDateTime, $endDateTime, $absolute);
		return $seconds / 3600;
	}

	/**
	 * Calculates the number of minutes between two DateTimes.
	 * @param \DateTime $startDateTime Start datetime
	 * @param \DateTime $endDateTime End datetime
	 * @param bool $absolute Whether to return absolute value (default: true)
	 * @return float Number of minutes between datetimes (can include decimals)
	 */
	public static function getMinutesBetween(\DateTime $startDateTime, \DateTime $endDateTime, bool $absolute = true): float
	{
		$seconds = self::getSecondsBetween($startDateTime, $endDateTime, $absolute);
		return $seconds / 60;
	}

	/**
	 * Calculates the number of seconds between two DateTimes.
	 * @param \DateTime $startDateTime Start datetime
	 * @param \DateTime $endDateTime End datetime
	 * @param bool $absolute Whether to return absolute value (default: true)
	 * @return int Number of seconds between datetimes
	 */
	public static function getSecondsBetween(\DateTime $startDateTime, \DateTime $endDateTime, bool $absolute = true): int
	{
		$diff = $endDateTime->getTimestamp() - $startDateTime->getTimestamp();
		return $absolute ? abs($diff) : $diff;
	}

	/**
	 * Calculates the number of seconds elapsed since midnight of a reference day.
	 * The reference day can differ from the day of $dateTime, which is useful for datetimes clipped to a range crossing midnight.
	 * @param \DateTime $dateTime The datetime to calculate seconds for
	 * @param \DateTime $day The reference day (only its date part is used)
	 * @return int Number of seconds since midnight of the reference day
	 */
	public static function getSecondsSinceMidnight(\DateTime $dateTime, \DateTime $day): int
	{
		return self::getSecondsBetween(self::startOfDay($day), $dateTime, false);
	}

	/**
	 * Gets the timestamp (Unix epoch seconds) for a DateTime.
	 * @param \DateTime $dateTime The datetime
	 * @return int Unix timestamp
	 */
	public static function getTimestamp(\DateTime $dateTime): int
	{
		return $dateTime->getTimestamp();
	}

	/**
	 * Gets the number of milliseconds since Unix epoch for a DateTime.
	 * @param \DateTime $dateTime The datetime
	 * @return int Milliseconds since Unix epoch
	 */
	public static function getMilliseconds(\DateTime $dateTime): int
	{
		return $dateTime->getTimestamp() * 1000;
	}

	// ========== Business Days Methods ==========

	/**
	 * Checks if a date is a working day (Monday-Friday, excluding weekends).
	 * Working day: Monday through Friday (excludes both Saturday and Sunday).
	 * Optionally excludes public holidays.
	 * @param \DateTime $dateTime The date to check
	 * @param bool $withPublicHoliday If true, also excludes public holidays (default: true)
	 * @return bool True if the date is a working day
	 */
	public static function isWorkingDay(\DateTime $dateTime, bool $withPublicHoliday=true): bool
	{
		if (self::isWeekend($dateTime)) {
			return false;
		}
		if ($withPublicHoliday && PublicHolidays::isPublicHoliday($dateTime)) {
			return false;
		}
		return true;
	}

	/**
	 * Checks if a date is a business day (Monday-Saturday, excluding Sundays only).
	 * Business day: Monday through Saturday (excludes only Sunday).
	 * Optionally excludes public holidays.
	 * @param \DateTime $dateTime The date to check
	 * @param bool $withPublicHoliday If true, also excludes public holidays (default: true)
	 * @return bool True if the date is a business day
	 */
	public static function isBusinessDay(\DateTime $dateTime, bool $withPublicHoliday=true): bool
	{
		$dayOfWeek = (int) $dateTime->format('N');
		if ($dayOfWeek === 7) {
			return false;
		}
		if ($withPublicHoliday && PublicHolidays::isPublicHoliday($dateTime)) {
			return false;
		}
		return true;
	}

	/**
	 * Calculates the number of business days (Monday-Friday) between two dates.
	 * Excludes weekends but does not account for holidays.
	 * @param \DateTime $startDate Start date (inclusive)
	 * @param \DateTime $endDate End date (inclusive)
	 * @return int Number of business days
	 */
	public static function getBusinessDays(\DateTime $startDate, \DateTime $endDate): int
	{
		$start = self::startOfDay($startDate);
		$end = self::startOfDay($endDate);

		if ($start > $end) {
			[$start, $end] = [$end, $start];
		}

		$businessDays = 0;
		$current = clone $start;

		while ($current <= $end) {
			if (self::isWeekday($current)) {
				$businessDays++;
			}
			$current = self::addDays($current, 1);
		}

		return $businessDays;
	}

	/**
	 * Adds a specified number of business days to a date.
	 * Skips weekends but does not account for holidays.
	 * @param \DateTime $dateTime The reference date
	 * @param int $businessDays Number of business days to add
	 * @return \DateTime New DateTime object with business days added
	 */
	public static function addBusinessDays(\DateTime $dateTime, int $businessDays): \DateTime
	{
		$result = clone $dateTime;
		$daysToAdd = $businessDays;

		while ($daysToAdd > 0) {
			$result = self::addDays($result, 1);
			if (self::isWeekday($result)) {
				$daysToAdd--;
			}
		}

		return $result;
	}

	/**
	 * Subtracts a specified number of business days from a date.
	 * Skips weekends but does not account for holidays.
	 * @param \DateTime $dateTime The reference date
	 * @param int $businessDays Number of business days to subtract
	 * @return \DateTime New DateTime object with business days subtracted
	 */
	public static function subBusinessDays(\DateTime $dateTime, int $businessDays): \DateTime
	{
		$result = clone $dateTime;
		$daysToSubtract = $businessDays;

		while ($daysToSubtract > 0) {
			$result = self::subDays($result, 1);
			if (self::isWeekday($result)) {
				$daysToSubtract--;
			}
		}

		return $result;
	}

	/**
	 * Sets a DateTime to the start of the day (00:00:00).
	 * Creates a clone to avoid modifying the original DateTime.
	 * @param \DateTime $dateTime The datetime to modify
	 * @return \DateTime DateTime at 00:00:00
	 */
	public static function startOfDay(\DateTime $dateTime): \DateTime
	{
		$result = clone $dateTime;
		$result->setTime(0, 0, 0);
		return $result;
	}

	// ========== Validation Methods ==========

	/**
	 * Checks if a date is within a valid range.
	 * @param \DateTime $dateTime The date to check
	 * @param \DateTime $minDate Minimum allowed date
	 * @param \DateTime $maxDate Maximum allowed date
	 * @return bool True if within range (inclusive), false otherwise
	 */
	public static function isValidRange(\DateTime $dateTime, \DateTime $minDate, \DateTime $maxDate): bool
	{
		return $dateTime >= $minDate && $dateTime <= $maxDate;
	}

	// ========== DEPRECATED METHODS ==========
	// Backward compatibility. Will be removed in a future major version. Please update your code to use the new method names.

	/**
	 * @deprecated Use DateTime::parse() instead
	 * @param string $str The date string to parse
	 * @return \DateTime|null A DateTime object if parsing succeeds, null otherwise
	 */
	public static function parseDate(string $str): ?\DateTime
	{
		return self::parse($str);
	}

	/**
	 * @deprecated use formatDateLong if $withWeekDay = false or formatDateFull if $withWeekDay = true instead
	 */
	public static function formatDateInLong(\DateTime $dateTime, ?string $locale=null, bool $withWeekDay=false): string
	{
		return $withWeekDay ? self::formatDateFull($dateTime, $locale) : self::formatDateLong($dateTime, $locale);
	}

}