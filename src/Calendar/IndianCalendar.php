<?php

namespace Osimatic\Calendar;

/**
 * Indian Civil Calendar (Saka Era) conversion and utility class.
 * The Indian national calendar, also called the Saka calendar, is used alongside the Gregorian calendar in India.
 * It is a solar calendar with 365 days in common years and 366 days in leap years.
 * The calendar starts on March 22 (or March 21 in leap years) of the Gregorian calendar.
 * @link https://www.fourmilab.ch/documents/calendar/calendar.js
 * @link https://stackoverflow.com/questions/24060864/converting-a-date-from-a-calendar-type-to-another-type
 * @link https://en.wikipedia.org/wiki/Hindu_calendar
 * @link https://en.wikipedia.org/wiki/Indian_national_calendar
 * @link https://stackoverflow.com/questions/8645956/converting-to-and-from-hindu-calendar
 * @link https://www.decordier-immobilier.mu/fr/actualites/details/1537/jours-feries-2025-ile-maurice/
 * @link https://www.cg972.fr/guide-ile-maurice/informations-pratiques/fetes-jours-feries
 */
class IndianCalendar
{
	/**
	 * Weekday names in the Indian Civil Calendar
	 */
	public const array INDIAN_CIVIL_WEEKDAYS = ["Ravivara", "Somavara", "Mangalavara", "Budhavara", "Brahaspativara", "Sukravara", "Sanivara"];

	/**
	 * Month names in the Indian Civil Calendar
	 */
	public const array INDIAN_MONTHS = ["Chaitra", "Vaisakha", "Jyaistha", "Asadha", "Sravana", "Bhadra", "Asvina", "Kartika", "Agrahayana", "Pausa", "Magha", "Phalguna"];

	/* ========== Timestamp Methods ========== */

	/**
	 * Get Unix timestamp for a given Indian Civil date and time.
	 * @param int $indianYear  Indian Civil year (Saka era)
	 * @param int $indianMonth Indian Civil month (1-12)
	 * @param int $indianDay   Indian Civil day (1-31)
	 * @param int $hour        Hour (0-23)
	 * @param int $minute      Minute (0-59)
	 * @param int $second      Second (0-59)
	 * @return int Unix timestamp (number of seconds since January 1 1970 00:00:00 GMT)
	 */
	public static function getTimestamp(int $indianYear, int $indianMonth, int $indianDay, int $hour = 0, int $minute = 0, int $second = 0): int
	{
		[$year, $month, $day] = self::convertIndianDateToGregorianDate($indianYear, $indianMonth, $indianDay);

		return mktime($hour, $minute, $second, $month, $day, $year);
	}

	/* ========== Conversion Methods ========== */

	/**
	 * Convert Indian Civil date to Gregorian date.
	 * @param int $year  Indian Civil year (Saka era)
	 * @param int $month Indian Civil month (1-12)
	 * @param int $day   Indian Civil day (1-31)
	 * @return int[] Gregorian date array: [year, month, day]
	 */
	public static function convertIndianDateToGregorianDate(int $year, int $month, int $day): array
	{
		$str = jdtogregorian(self::convertIndianDateToJd($year, $month, $day));

		[$monthStr, $dayStr, $yearStr] = explode('/', $str);

		return [(int)$yearStr, (int)$monthStr, (int)$dayStr];
	}

	/**
	 * Convert Gregorian date to Indian Civil date.
	 * @param int $year  Gregorian year
	 * @param int $month Gregorian month (1-12)
	 * @param int $day   Gregorian day (1-31)
	 * @return int[] Indian Civil date array: [year, month, day]
	 * @author Khaled Al-Sham'aa <khaled@ar-php.org>
	 */
	public static function convertGregorianDateToIndianDate(int $year, int $month, int $day): array
	{
		$jd = gregoriantojd($month, $day, $year);

		[$indianYear, $indianMonth, $indianDay] = self::convertJdToIndianDate($jd);

		return [$indianYear, $indianMonth, $indianDay];
	}

	/**
	 * Convert Unix timestamp to Indian Civil date.
	 * @param int $timestamp Unix timestamp
	 * @return int[] Indian Civil date array: [year, month, day]
	 */
	public static function convertTimestampToIndianDate(int $timestamp): array
	{
		return self::convertGregorianDateToIndianDate((int)date('Y', $timestamp), (int)date('m', $timestamp), (int)date('d', $timestamp));
	}

	/* ========== Validation Methods ========== */

	/**
	 * Check if a given Indian Civil date is valid.
	 * @param int $year  Indian Civil year
	 * @param int $month Indian Civil month (1-12)
	 * @param int $day   Indian Civil day (1-31)
	 * @return bool True if the date is valid, false otherwise
	 */
	public static function isValidDate(int $year, int $month, int $day): bool
	{
		if ($year < 0) {
			return false;
		}

		if ($month < 1 || $month > 12) {
			return false;
		}

		$daysInMonth = self::getDaysInMonth($year, $month);
		if ($day < 1 || $day > $daysInMonth) {
			return false;
		}

		return true;
	}

	/* ========== Utility Methods ========== */

	/**
	 * Get the name of an Indian Civil month.
	 * @param int $month Month number (1-12)
	 * @return string|null Month name, or null if invalid month
	 */
	public static function getMonthName(int $month): ?string
	{
		if ($month < 1 || $month > 12) {
			return null;
		}

		return self::INDIAN_MONTHS[$month - 1];
	}

	/**
	 * Get the name of a weekday in the Indian Civil Calendar.
	 * @param int $weekday Weekday number (0=Sunday, 1=Monday, ..., 6=Saturday)
	 * @return string|null Weekday name, or null if invalid weekday
	 */
	public static function getWeekdayName(int $weekday): ?string
	{
		if ($weekday < 0 || $weekday > 6) {
			return null;
		}

		return self::INDIAN_CIVIL_WEEKDAYS[$weekday];
	}

	/**
	 * Get the number of days in a given Indian Civil month.
	 * The first month (Chaitra) has 30 days in common years and 31 days in leap years.
	 * Months 2-6 have 31 days, and months 7-12 have 30 days.
	 * @param int $year  Indian Civil year
	 * @param int $month Indian Civil month (1-12)
	 * @return int|null Number of days in the month, or null if invalid month
	 */
	public static function getDaysInMonth(int $year, int $month): ?int
	{
		if ($month < 1 || $month > 12) {
			return null;
		}

		// Month 1 (Chaitra) has 30 or 31 days depending on leap year
		if ($month === 1) {
			$gregorianYear = $year + 78;
			return Date::isLeapYear($gregorianYear) ? 31 : 30;
		}

		// Months 2-6 have 31 days
		if ($month >= 2 && $month <= 6) {
			return 31;
		}

		// Months 7-12 have 30 days
		return 30;
	}

	/**
	 * Format an Indian Civil date as a string.
	 * @param int    $year   Indian Civil year
	 * @param int    $month  Indian Civil month (1-12)
	 * @param int    $day    Indian Civil day (1-31)
	 * @param string $format Format string: 'full' (e.g., "Somavara 15 Chaitra 1945"), 'long' (e.g., "15 Chaitra 1945"), 'medium' (e.g., "15 Cha 1945"), 'short' (e.g., "15/1/1945"), 'iso' (e.g., "1945-01-15")
	 * @return string|null Formatted date string, or null if invalid date or format
	 */
	public static function format(int $year, int $month, int $day, string $format = 'long'): ?string
	{
		if (!self::isValidDate($year, $month, $day)) {
			return null;
		}

		// Get weekday name for full format
		if ($format === 'full') {
			[$gregYear, $gregMonth, $gregDay] = self::convertIndianDateToGregorianDate($year, $month, $day);
			$weekday = (int)date('w', mktime(0, 0, 0, $gregMonth, $gregDay, $gregYear));
			$weekdayName = self::getWeekdayName($weekday);
			return sprintf('%s %d %s %d', $weekdayName, $day, self::getMonthName($month), $year);
		}

		return match($format) {
			'long' => sprintf('%d %s %d', $day, self::getMonthName($month), $year),
			'medium' => sprintf('%d %s %d', $day, substr(self::getMonthName($month), 0, 3), $year),
			'short' => sprintf('%d/%d/%d', $day, $month, $year),
			'iso' => sprintf('%04d-%02d-%02d', $year, $month, $day),
			default => null,
		};
	}

	/* ========== Private Methods ========== */

	/**
	 * Convert Indian Civil date to Julian Day number.
	 * The Julian Day Count is a continuous count of days starting from January 1, 4713 BC (proleptic Julian calendar).
	 * @param int $year  Indian Civil year (Saka era)
	 * @param int $month Indian Civil month (1-12)
	 * @param int $day   Indian Civil day (1-31)
	 * @return int Julian Day number
	 */
	private static function convertIndianDateToJd(int $year, int $month, int $day): int
	{
		// The Indian calendar is offset by 78 years from the Gregorian calendar
		$gregorianYear = $year + 78;
		$isLeapYear = Date::isLeapYear($gregorianYear);

		// The Indian year starts on March 21 (or March 22 in leap years)
		$start = cal_to_jd(CAL_GREGORIAN, 3, $isLeapYear ? 21 : 22, $gregorianYear);
		$caitra = $isLeapYear ? 31 : 30;

		// First month (Chaitra)
		if ($month === 1) {
			return $start + ($day - 1);
		}

		// Calculate Julian Day for other months
		$jd = $start + $caitra;
		$monthsAfterChaitra = $month - 2;
		$monthsAfterChaitra = min($monthsAfterChaitra, 5);
		$jd += $monthsAfterChaitra * 31;

		// Months 7-12 have 30 days each
		if ($month >= 8) {
			$monthsAfter7 = $month - 7;
			$jd += $monthsAfter7 * 30;
		}
		$jd += $day - 1;

		return $jd;
	}

	/**
	 * Convert Julian Day number to Indian Civil date.
	 * The Julian Day Count is a continuous count of days starting from January 1, 4713 BC (proleptic Julian calendar).
	 * @param int $jd Julian Day number
	 * @return int[] Indian Civil date array: [year, month, day]
	 */
	private static function convertJdToIndianDate(int $jd): array
	{
		// Offset in years from Saka era to Gregorian epoch
		$sakaOffset = 79 - 1;
		// Day offset between Saka and Gregorian calendars
		$dayOffset = 80;

		// Normalize Julian Day (handles fractional days)
		$jdFloat = floor($jd) + 0.5;

		// Get Gregorian date for Julian Day
		$gregorianDate = cal_from_jd((int)$jdFloat, CAL_GREGORIAN);
		$gregorianYear = $gregorianDate['year'];
		$isLeapYear = Date::isLeapYear($gregorianYear);

		// Calculate tentative year in Saka era
		$indianYear = $gregorianYear - $sakaOffset;

		// Get Julian Day at start of Gregorian year
		$gregorianYearStart = cal_to_jd(CAL_GREGORIAN, 1, 1, $gregorianYear);

		// Day number (0-based) in Gregorian year
		$dayOfYear = $jdFloat - $gregorianYearStart;

		// Days in Chaitra this year
		$chaitraDays = $isLeapYear ? 31 : 30;

		// Check if day is at the end of the preceding Saka year
		if ($dayOfYear < $dayOffset) {
			$indianYear--;
			$dayOfYear += $chaitraDays + (31 * 5) + (30 * 3) + 10 + $dayOffset;
		}

		$dayOfYear -= $dayOffset;

		// Calculate month and day
		if ($dayOfYear < $chaitraDays) {
			$indianMonth = 1;
			$indianDay = (int)($dayOfYear + 1);
		} else {
			$remainingDays = $dayOfYear - $chaitraDays;
			if ($remainingDays < (31 * 5)) {
				$indianMonth = (int)(floor($remainingDays / 31) + 2);
				$indianDay = (int)((((int)$remainingDays) % 31) + 1);
			} else {
				$remainingDays -= 31 * 5;
				$indianMonth = (int)(floor($remainingDays / 30) + 7);
				$indianDay = (int)((((int)$remainingDays) % 30) + 1);
			}
		}

		return [$indianYear, $indianMonth, $indianDay];
	}
}