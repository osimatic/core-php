<?php

namespace Osimatic\Calendar;

/**
 * Utility class for timezone validation, formatting, and operations.
 * This class provides comprehensive timezone manipulation methods.
 *
 * Organized categories:
 * - Current Timezone: Get default/current timezone
 * - Validation: Validate timezone identifiers
 * - Information: Get timezone properties (offset, abbreviation, DST status)
 * - Lookup: Find timezones by criteria
 * - Formatting: Format timezone strings with various options
 * - Configuration: Access timezone configuration data
 */
class Timezone
{
	// ========== Current Timezone Methods ==========

	/**
	 * Gets the default timezone set in PHP configuration.
	 * @return string The default timezone identifier (e.g., 'Europe/Paris', 'UTC')
	 */
	public static function getCurrentTimezone(): string
	{
		return date_default_timezone_get();
	}

	/**
	 * Sets the default timezone for all date/time functions.
	 * @param string $timezone The timezone identifier to set
	 * @return bool True if timezone was set successfully, false otherwise
	 */
	public static function setCurrentTimezone(string $timezone): bool
	{
		// date_default_timezone_set() emits a notice on unknown identifiers, so validate them first
		if (!in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
			return false;
		}

		try {
			return date_default_timezone_set($timezone);
		} catch (\Exception) {
			return false;
		}
	}

	// ========== Validation Methods ==========

	/**
	 * Validates if a timezone identifier is valid.
	 * Uses Symfony Validator component for validation.
	 * Optionally validates timezone against a specific country code.
	 * @param string $timezone The timezone identifier (e.g., 'Europe/Paris', 'America/New_York')
	 * @param string|null $countryCode Optional ISO 3166-1 alpha-2 country code (e.g., 'FR', 'US')
	 * @return bool True if timezone is valid (and matches country if specified), false otherwise
	 * @link https://www.php.net/manual/en/timezones.php
	 */
	public static function isValid(string $timezone, ?string $countryCode = null): bool
	{
		if (empty($timezone)) {
			return false;
		}

		$constraint = new \Symfony\Component\Validator\Constraints\Timezone();
		$constraint->countryCode = $countryCode;
		$constraint->zone = null !== $countryCode ? \DateTimeZone::PER_COUNTRY : \DateTimeZone::ALL;
		return \Osimatic\Validator\Validator::getInstance()->validate($timezone, $constraint)->count() === 0;
	}

	// ========== Information Methods ==========

	/**
	 * Gets the UTC offset in seconds for a timezone at a specific date/time.
	 * @param string $timezone The timezone identifier
	 * @param \DateTime|null $dateTime Optional date/time to calculate offset for (default: now)
	 * @return int The offset in seconds from UTC (positive for east, negative for west)
	 */
	public static function getOffset(string $timezone, ?\DateTime $dateTime = null): int
	{
		try {
			$tz = new \DateTimeZone($timezone);
			$dt = $dateTime ?? new \DateTime('now');
			return $tz->getOffset($dt);
		} catch (\Exception) {
			return 0;
		}
	}

	/**
	 * Gets the timezone abbreviation (e.g., 'EST', 'PST', 'CET') for a specific date/time.
	 * @param string $timezone The timezone identifier
	 * @param \DateTime|null $dateTime Optional date/time to get abbreviation for (default: now)
	 * @return string The timezone abbreviation
	 */
	public static function getAbbreviation(string $timezone, ?\DateTime $dateTime = null): string
	{
		try {
			$dt = $dateTime ?? new \DateTime('now');
			$dt->setTimezone(new \DateTimeZone($timezone));
			return $dt->format('T');
		} catch (\Exception) {
			return '';
		}
	}

	/**
	 * Checks if daylight saving time (DST) is currently active for a timezone.
	 * @param string $timezone The timezone identifier
	 * @param \DateTime|null $dateTime Optional date/time to check DST for (default: now)
	 * @return bool True if DST is active, false otherwise
	 */
	public static function isDaylightSavingTime(string $timezone, ?\DateTime $dateTime = null): bool
	{
		try {
			$dt = $dateTime ?? new \DateTime('now');
			$dt->setTimezone(new \DateTimeZone($timezone));
			return (bool) $dt->format('I');
		} catch (\Exception) {
			return false;
		}
	}

	/**
	 * Gets the offset formatted as a string (e.g., '+01:00', '-05:00', '+00:00').
	 * @param string $timezone The timezone identifier
	 * @param \DateTime|null $dateTime Optional date/time to calculate offset for (default: now)
	 * @return string The formatted offset string
	 */
	public static function getOffsetFormatted(string $timezone, ?\DateTime $dateTime = null): string
	{
		$offsetSeconds = self::getOffset($timezone, $dateTime);
		$hours = (int) floor(abs($offsetSeconds) / 3600);
		$minutes = (int) floor((abs($offsetSeconds) % 3600) / 60);
		$sign = $offsetSeconds >= 0 ? '+' : '-';
		return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
	}

	/**
	 * Gets all DST transition times for a timezone.
	 * @param string $timezone The timezone identifier
	 * @param int|null $timestampBegin Start timestamp for transitions (default: current year start)
	 * @param int|null $timestampEnd End timestamp for transitions (default: next year end)
	 * @return array Array of transition data with time, offset, isdst, and abbr keys
	 */
	public static function getTransitions(string $timezone, ?int $timestampBegin = null, ?int $timestampEnd = null): array
	{
		try {
			$tz = new \DateTimeZone($timezone);
			$timestampBegin = $timestampBegin ?? mktime(0, 0, 0, 1, 1, (int) date('Y'));
			$timestampEnd = $timestampEnd ?? mktime(23, 59, 59, 12, 31, (int) date('Y') + 1);
			return $tz->getTransitions($timestampBegin, $timestampEnd);
		} catch (\Exception) {
			return [];
		}
	}

	// ========== Lookup Methods ==========

	/**
	 * Gets all available timezone identifiers.
	 * @param int $group Optional DateTimeZone constant to filter by group (default: ALL)
	 * @return array Array of timezone identifiers
	 */
	public static function getAllTimezones(int $group = \DateTimeZone::ALL): array
	{
		return \DateTimeZone::listIdentifiers($group);
	}

	/**
	 * Gets timezones filtered by UTC offset.
	 * @param int $offsetSeconds The UTC offset in seconds to filter by
	 * @param \DateTime|null $dateTime Optional date/time for offset calculation (default: now)
	 * @return array Array of timezone identifiers matching the offset
	 */
	public static function getTimezonesByOffset(int $offsetSeconds, ?\DateTime $dateTime = null): array
	{
		$matchingTimezones = [];
		$allTimezones = self::getAllTimezones();

		foreach ($allTimezones as $timezone) {
			if (self::getOffset($timezone, $dateTime) === $offsetSeconds) {
				$matchingTimezones[] = $timezone;
			}
		}

		return $matchingTimezones;
	}

	/**
	 * Gets all timezone identifiers for a specific country.
	 * Uses PHP's DateTimeZone::listIdentifiers() for country-specific timezones.
	 * @param string $countryCode ISO 3166-1 alpha-2 country code (e.g., 'FR', 'US')
	 * @return array Array of timezone identifiers for the country, empty array if invalid
	 */
	public static function getTimezonesByCountry(string $countryCode): array
	{
		if (empty($countryCode) || strlen($countryCode) !== 2) {
			return [];
		}

		try {
			return \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $countryCode);
		} catch (\ValueError) {
			return [];
		}
	}

	/**
	 * Gets the primary timezone identifier for a country.
	 * Returns the first timezone in the country's timezone list.
	 * @param string $countryCode ISO 3166-1 alpha-2 country code (e.g., 'FR', 'US')
	 * @return string|null The primary timezone identifier, or null if country has no timezones
	 */
	public static function getPrimaryTimezoneOfCountry(string $countryCode): ?string
	{
		return self::getTimezonesByCountry($countryCode)[0] ?? null;
	}

	// ========== Formatting Methods ==========

	/**
	 * Formats a timezone identifier with UTC offset and optionally country and cities.
	 * Looks up timezone data from internal configuration.
	 * @param string $timezone The timezone identifier (e.g., 'Europe/Paris')
	 * @param bool $withCountry Whether to include country name in output (default: true)
	 * @param bool $withCities Whether to include city names in output (default: true)
	 * @return string Formatted timezone string (e.g., "UTC+01:00 - Europe/Paris (France : Paris)")
	 */
	public static function format(string $timezone, bool $withCountry = true, bool $withCities = true): string
	{
		$timezone = mb_strtolower($timezone);
		$listTimeZones = self::getConfigurationData();

		foreach ($listTimeZones as $timezoneName => $timezoneData) {
			if (mb_strtolower($timezoneName) !== $timezone) {
				continue;
			}

			return self::formatWithData($timezoneName, $timezoneData['utc'], $timezoneData['country'], $timezoneData['cities'] ?? [], $withCountry, $withCities);
		}

		return '';
	}

	/**
	 * Formats timezone information with provided data.
	 * Creates a formatted string with UTC offset, timezone name, and optionally country and cities.
	 * @param string $timezoneName The timezone identifier (e.g., 'Europe/Paris')
	 * @param string $utc The UTC offset (e.g., 'UTC+01:00')
	 * @param string|null $countryCodeOrCountryName Country code or full country name
	 * @param string[] $cities Array of city names in this timezone
	 * @param bool $withCountry Whether to include country in output (default: true)
	 * @param bool $withCities Whether to include cities in output (default: true)
	 * @return string Formatted timezone string (e.g., "UTC+01:00 - Europe/Paris (France : Paris, Lyon)")
	 */
	public static function formatWithData(string $timezoneName, string $utc, ?string $countryCodeOrCountryName = null, array $cities = [], bool $withCountry = true, bool $withCities = true): string
	{
		$withCities = $withCities && !empty($cities);
		$withCountry = $withCountry && !empty($countryCodeOrCountryName);
		$str = $utc . ' - ' . $timezoneName;
		if ($withCountry || $withCities) {
			$str .= ' (';
			if ($withCountry) {
				$str .= (\Osimatic\Location\Country::getCountryNameFromCountryCode($countryCodeOrCountryName) ?? $countryCodeOrCountryName);
			}
			if ($withCountry && $withCities) {
				$str .= ' : ';
			}
			if ($withCities) {
				$str .= implode(', ', $cities);
			}
			$str .= ')';
		}
		return $str;
	}

	/**
	 * Gets formatted labels for all timezones from configuration.
	 * Returns an associative array mapping timezone identifiers to formatted strings.
	 * @param bool $withCountry Whether to include country names in labels (default: true)
	 * @param bool $withCities Whether to include city names in labels (default: true)
	 * @return string[] Array of [timezone => formatted_label]
	 */
	public static function getFormattedLabels(bool $withCountry = true, bool $withCities = true): array
	{
		$listTimeZones = self::getConfigurationData();

		$listTimeZonesLabel = [];
		foreach ($listTimeZones as $timezoneName => $timezoneData) {
			$listTimeZonesLabel[$timezoneName] = self::formatWithData($timezoneName, $timezoneData['utc'], $timezoneData['country'], $timezoneData['cities'] ?? [], $withCountry, $withCities);
		}
		return $listTimeZonesLabel;
	}

	// ========== Configuration Methods ==========

	/**
	 * Gets all timezone data from configuration file.
	 * Loads timezone information including UTC offsets, countries, and cities.
	 * @return array Array of timezone data from time_zones.ini configuration file
	 */
	public static function getConfigurationData(): array
	{
		return parse_ini_file(__DIR__ . '/conf/time_zones.ini', true);
	}

	// ========== DEPRECATED METHODS ==========
	// Backward compatibility. Will be removed in a future major version. Please update your code to use the new method names.

	/**
	 * @deprecated Use isValid() instead
	 * @param string $timezone The timezone identifier
	 * @param string|null $countryCode Optional country code
	 * @return bool True if valid, false otherwise
	 */
	public static function check(string $timezone, ?string $countryCode = null): bool
	{
		return self::isValid($timezone, $countryCode);
	}

	/**
	 * @deprecated Use getTimezonesByCountry() instead
	 * @param string $countryCode ISO 3166-1 alpha-2 country code
	 * @return array Array of timezone identifiers
	 */
	public static function getListTimeZonesOfCountry(string $countryCode): array
	{
		return self::getTimezonesByCountry($countryCode);
	}

	/**
	 * @deprecated Use getPrimaryTimezoneOfCountry() instead
	 * @param string $countryCode ISO 3166-1 alpha-2 country code
	 * @return string|null The primary timezone identifier
	 */
	public static function getTimeZoneOfCountry(string $countryCode): ?string
	{
		return self::getPrimaryTimezoneOfCountry($countryCode);
	}

	/**
	 * @deprecated Use getFormattedLabels() instead
	 * @param bool $withCountry Whether to include country names
	 * @param bool $withCities Whether to include city names
	 * @return string[] Array of formatted labels
	 */
	public static function getListTimeZonesLabel(bool $withCountry = true, bool $withCities = true): array
	{
		return self::getFormattedLabels($withCountry, $withCities);
	}

	/**
	 * @deprecated Use getConfigurationData() instead
	 * @return array Array of timezone data
	 */
	public static function getListTimeZones(): array
	{
		return self::getConfigurationData();
	}
}