<?php

namespace Osimatic\Number;

/**
 * Class Duration
 * Provides utilities for duration calculations, formatting, parsing, and validation
 */
class Duration
{
	// ========== Calculations Between Two Time Slots ==========

	/**
	 * Calculates the duration (in seconds) of the intersection between two time slots
	 * @param int $timeSlot1StartTimestamp start timestamp of first time slot
	 * @param int $timeSlotEnd1Timestamp end timestamp of first time slot
	 * @param int $timeSlot2StartTimestamp start timestamp of second time slot
	 * @param int $timeSlotEnd2Timestamp end timestamp of second time slot
	 * @return int the duration of the intersection in seconds, 0 if no intersection
	 */
	public static function getIntersectionDurationBetweenTimestampSlots(
		int $timeSlot1StartTimestamp,
		int $timeSlotEnd1Timestamp,
		int $timeSlot2StartTimestamp,
		int $timeSlotEnd2Timestamp
	): int
	{
		$timestampCalcStart = ($timeSlot1StartTimestamp > $timeSlot2StartTimestamp ? $timeSlot1StartTimestamp : $timeSlot2StartTimestamp);
		$timestampCalcEnd = ($timeSlotEnd1Timestamp < $timeSlotEnd2Timestamp ? $timeSlotEnd1Timestamp : $timeSlotEnd2Timestamp);
		if ($timestampCalcEnd > $timestampCalcStart) {
			return $timestampCalcEnd - $timestampCalcStart;
		}
		return 0;
	}

	/**
	 * Calculates the duration (in seconds) of the intersection between two time slots
	 * @param \DateTimeInterface $slot1Start start of first time slot
	 * @param \DateTimeInterface $slot1End end of first time slot
	 * @param \DateTimeInterface $slot2Start start of second time slot
	 * @param \DateTimeInterface $slot2End end of second time slot
	 * @return int duration of the intersection in seconds, 0 if no intersection
	 */
	public static function getIntersectionDurationBetweenTimeSlots(
		\DateTimeInterface $slot1Start,
		\DateTimeInterface $slot1End,
		\DateTimeInterface $slot2Start,
		\DateTimeInterface $slot2End
	): int
	{
		// Determine the later of the two start times
		$calcStart = $slot1Start > $slot2Start ? $slot1Start : $slot2Start;
		// Determine the earlier of the two end times
		$calcEnd = $slot1End < $slot2End ? $slot1End : $slot2End;

		// If there is an intersection
		if ($calcEnd > $calcStart) {
			return self::getDurationInSeconds($calcStart, $calcEnd);
		}

		return 0;
	}

	/**
	 * Returns the duration between two DateTime objects in seconds, correctly handling daylight saving time transitions
	 * @param \DateTimeInterface $start the start datetime
	 * @param \DateTimeInterface $end the end datetime
	 * @param bool $inverse if true, swap start and end if end is before start (default: false)
	 * @return int duration in seconds (always positive), 0 if end is before start and inverse is false
	 */
	public static function getDurationInSeconds(\DateTimeInterface $start, \DateTimeInterface $end, bool $inverse=false): int
	{
		// Ensure $end is after $start
		if ($end < $start) {
			if (!$inverse) {
				return 0;
			}

			[$start, $end] = [$end, $start];
		}

		return $end->getTimestamp() - $start->getTimestamp();

		/*$interval = $start->diff($end);
		return $interval->days * 86400
			+ $interval->h * 3600
			+ $interval->i * 60
			+ $interval->s;*/
	}

	// ========== Element Count Calculations ==========

	/**
	 * Gets the number of complete days in a duration
	 * @param int $durationInSeconds the duration in seconds
	 * @return int the number of complete days
	 */
	public static function getNbDays(int $durationInSeconds): int
	{
		return (int) floor($durationInSeconds / 86400);
	}

	/**
	 * Gets the total number of complete hours in a duration
	 * @param int $durationInSeconds the duration in seconds
	 * @return int the total number of complete hours
	 */
	public static function getNbHours(int $durationInSeconds): int
	{
		return (int) floor($durationInSeconds / 3600);
	}

	/**
	 * Gets the total number of complete minutes in a duration
	 * @param int $durationInSeconds the duration in seconds
	 * @return int the total number of complete minutes
	 */
	public static function getNbMinutes(int $durationInSeconds): int
	{
		return (int) floor($durationInSeconds / 60);
	}

	/**
	 * Gets the number of complete hours remaining (after removing days) in a duration
	 * @example for "1 day 2 hours 3 minutes and 4 seconds" (93784 seconds), this returns 2
	 * @param int $durationInSeconds the duration in seconds
	 * @return int the number of complete hours remaining
	 */
	public static function getNbHoursRemaining(int $durationInSeconds): int
	{
		$nbSecondsRemaining = $durationInSeconds%86400;
		return self::getNbHours($nbSecondsRemaining);
	}

	/**
	 * Gets the number of complete minutes remaining (after removing days and hours) in a duration
	 * @example for "1 day 2 hours and 3 minutes" (93780 seconds), this returns 3
	 * @param int $durationInSeconds the duration in seconds
	 * @return int the number of complete minutes remaining
	 */
	public static function getNbMinutesRemaining(int $durationInSeconds): int
	{
		$nbSecondsRemaining = $durationInSeconds%3600;
		return self::getNbMinutes($nbSecondsRemaining);
	}

	/**
	 * Gets the number of seconds remaining (after removing days, hours and minutes) in a duration
	 * @example for "1 day 2 hours 3 minutes and 4 seconds" (93784 seconds), this returns 4
	 * @param int $durationInSeconds the duration in seconds
	 * @return int the number of seconds remaining
	 */
	public static function getNbSecondsRemaining(int $durationInSeconds): int
	{
		return $durationInSeconds%60;
	}

	// ========== Duration Display ==========

	/**
	 * Formats a duration in seconds into a human-readable string.
	 * Supports multiple display modes: standard clock format, chrono notation, decimal hours, and raw seconds.
	 * Negative durations are displayed with a leading "- " prefix; the absolute value is used for formatting.
	 *
	 * @param int $durationInSeconds the duration in seconds to format (negative values are supported)
	 * @param DurationDisplayMode $displayMode display mode: STANDARD for "10:20.03", INPUT_TIME for "10:20:03", CHRONO for "10:20'03\"", DECIMAL for "10.34", SECONDS for raw seconds (default: STANDARD)
	 * @param bool $withSeconds whether to include seconds in the formatted duration (default: true, ignored in DECIMAL and SECONDS modes)
	 * @param bool $withSign whether to always show the sign ("+ " for positive, "- " for negative); if false, only negative durations show the "- " prefix (default: false)
	 * @return string the formatted duration string
	 */
	public static function format(int $durationInSeconds, DurationDisplayMode $displayMode=DurationDisplayMode::STANDARD, bool $withSeconds=true, bool $withSign=false): string
	{
		$isNegative = $durationInSeconds < 0;
		$absoluteDuration = abs($durationInSeconds);

		if ($withSign) {
			$sign = $isNegative ? '- ' : '+ ';
		} else {
			$sign = $isNegative ? '- ' : '';
		}

		if ($displayMode === DurationDisplayMode::DECIMAL) {
			return $sign . Number::format(self::convertToNbDecimalHours($absoluteDuration), 2);
		}
		if ($displayMode === DurationDisplayMode::SECONDS) {
			return $sign . $absoluteDuration;
		}
		return $sign . self::formatNbHours($absoluteDuration, $displayMode, $withSeconds);
	}

	/**
	 * Formats a duration as text with hours, minutes, and seconds
	 * @param int $durationInSeconds the duration in seconds
	 * @param bool $withSeconds whether to include seconds (default: true)
	 * @param bool $withMinutes whether to include minutes (default: true)
	 * @param bool $withMinuteLabel whether to add minute label (default: true)
	 * @param bool $fullLabel whether to use full labels (e.g., "hours" vs "h") (default: false)
	 * @param bool $hideHourIfZeroHour whether to hide hours if zero (default: false)
	 * @return string the formatted duration string
	 */
	public static function formatAsText(int $durationInSeconds, bool $withSeconds=true, bool $withMinutes=true, bool $withMinuteLabel=true, bool $fullLabel=false, bool $hideHourIfZeroHour=false): string
	{
		$durationInSeconds = (int) abs($durationInSeconds);

		$str = '';

		// Heures
		$nbHours = self::getNbHours($durationInSeconds);
		if (!$hideHourIfZeroHour || $nbHours > 0) {
			$str .= $nbHours;
			$str .= $fullLabel ? ' '.\Osimatic\Text\Str::pluralize('{heure|heures}', $nbHours) : 'h';
		}

		// Minutes
		if ($withMinutes) {
			$nbMinutes = self::getNbMinutesRemaining($durationInSeconds);
			$str .= ' ' . str_pad($nbMinutes, 2, '0', STR_PAD_LEFT);
			if ($withMinuteLabel) {
				$str .= $fullLabel ? ' '.\Osimatic\Text\Str::pluralize('{minute|minutes}', $nbMinutes) : 'min';
			}
		}

		// Secondes
		if ($withSeconds) {
			$nbSeconds = self::getNbSecondsRemaining($durationInSeconds);
			$str .= ' ' . str_pad($nbSeconds, 2, '0', STR_PAD_LEFT);
			$str .= $fullLabel ? ' '.\Osimatic\Text\Str::pluralize('{seconde|secondes}', $nbSeconds) : 's';
		}

		return trim($str);
	}

	// ========== Duration Display (Chrono Format) ==========

	/**
	 * Formats a duration in hours for display in chrono format (e.g., "10:20.03" or "10:20'03")
	 * @param int $durationInSeconds the duration in seconds to format
	 * @param DurationDisplayMode $displayMode display mode: STANDARD for "10:20.03", INPUT_TIME for "10:20:03", or CHRONO for "10:20'03" (default: STANDARD)
	 * @param bool $withSeconds whether to include seconds in the formatted duration (default: true)
	 * @return string the formatted duration string
	 */
	public static function formatNbHours(int $durationInSeconds, DurationDisplayMode $displayMode=DurationDisplayMode::STANDARD, bool $withSeconds=true): string
	{
		$sign = $durationInSeconds < 0 ? '-' : '';
		$durationInSeconds = abs($durationInSeconds);

		// Hours
		$strHour = sprintf('%02d', self::getNbHours($durationInSeconds)).':';

		// Minutes
		$strMinute = self::getFormattedNbMinutes(self::getNbMinutesRemaining($durationInSeconds), $displayMode);

		// Seconds
		$strSecond = '';
		if ($withSeconds) {
			$strSecond = self::getFormattedNbSeconds(self::getNbSecondsRemaining($durationInSeconds), $displayMode);
		}

		return $sign.$strHour.$strMinute.$strSecond;
	}

	/**
	 * Formats a duration in minutes for display in chrono format
	 * @param int $durationInSeconds the duration in seconds to format
	 * @param DurationDisplayMode $displayMode display mode: STANDARD for "10.03", INPUT_TIME for "10:03", or CHRONO for "10'03" (default: STANDARD)
	 * @return string the formatted duration string
	 */
	public static function formatNbMinutes(int $durationInSeconds, DurationDisplayMode $displayMode=DurationDisplayMode::STANDARD): string
	{
		$sign = $durationInSeconds < 0 ? '-' : '';
		$durationInSeconds = abs($durationInSeconds);

		// Minutes
		$strMinute = self::getFormattedNbMinutes(self::getNbMinutes($durationInSeconds), $displayMode);

		// Seconds
		$strSecond = self::getFormattedNbSeconds(self::getNbSecondsRemaining($durationInSeconds), $displayMode);

		return $sign.$strMinute.$strSecond;
	}

	/**
	 * Formats the number of minutes according to display mode
	 * @param int $nbMinutes the number of minutes
	 * @param DurationDisplayMode $displayMode the display mode
	 * @return string the formatted minutes string
	 */
	private static function getFormattedNbMinutes(int $nbMinutes, DurationDisplayMode $displayMode=DurationDisplayMode::STANDARD): string
	{
		return sprintf('%02d', $nbMinutes).($displayMode===DurationDisplayMode::CHRONO?'\'':'');
	}

	/**
	 * Formats the number of seconds according to display mode
	 * @param int $nbSeconds the number of seconds
	 * @param DurationDisplayMode $displayMode the display mode
	 * @return string the formatted seconds string
	 */
	private static function getFormattedNbSeconds(int $nbSeconds, DurationDisplayMode $displayMode=DurationDisplayMode::STANDARD): string
	{
		return ($displayMode===DurationDisplayMode::INPUT_TIME?':':($displayMode!==DurationDisplayMode::CHRONO?'.':'')).sprintf('%02d', $nbSeconds).($displayMode===DurationDisplayMode::CHRONO?'"':'');
	}


	// ========== Validation ==========

	/**
	 * Validates a duration entered in a form field (text or time input)
	 * Accepts durations in formats like "10:23:02" or "1220" (seconds)
	 * @param mixed $enteredDuration the entered duration value
	 * @param string $separator the separator character (default: ':')
	 * @param int $hourPos the position of hours (default: 1)
	 * @param int $minutePos the position of minutes (default: 2)
	 * @param int $secondPos the position of seconds (default: 3)
	 * @return bool true if valid duration, false otherwise
	 */
	public static function isValid(mixed $enteredDuration, string $separator=':', int $hourPos=1, int $minutePos=2, int $secondPos=3): bool
	{
		return (null !== self::_parse($enteredDuration, $separator, $hourPos, $minutePos, $secondPos));
	}

	// ========== Parsing ==========

	/**
	 * Parses a duration in integer format (seconds) or string format (hh:mm:ss) and returns duration in seconds
	 * @param mixed $enteredDuration the entered duration value
	 * @param string $separator the separator character (default: ':')
	 * @param int $hourPos the position of hours (default: 1)
	 * @param int $minutePos the position of minutes (default: 2)
	 * @param int $secondPos the position of seconds (default: 3)
	 * @return int the duration in seconds, 0 if invalid
	 */
	public static function parse(mixed $enteredDuration, string $separator=':', int $hourPos=1, int $minutePos=2, int $secondPos=3): int
	{
		if (null !== ($duration = self::_parse($enteredDuration, $separator, $hourPos, $minutePos, $secondPos))) {
			return $duration;
		}
		return 0;
	}

	/**
	 * Internal parsing method
	 * @param mixed $enteredDuration the entered duration value
	 * @param string $separator the separator character
	 * @param int $hourPos the position of hours
	 * @param int $minutePos the position of minutes
	 * @param int $secondPos the position of seconds
	 * @return int|null the duration in seconds, null if invalid
	 */
	private static function _parse(mixed $enteredDuration, string $separator=':', int $hourPos=1, int $minutePos=2, int $secondPos=3): ?int
	{
		if (preg_match("/^-?[0-9]{0,10}$/", $enteredDuration)) {
			return (int) $enteredDuration;
		}

		if (null === ($timeArray = \Osimatic\Calendar\Time::_parse($enteredDuration, $separator, $hourPos, $minutePos, $secondPos))) {
			return null;
		}

		return ($timeArray[0] ?? 0) * 3600 + ($timeArray[1] ?? 0) * 60 + ($timeArray[2] ?? 0);
	}

	// ========== Conversion to Decimal Duration ==========

	/**
	 * Converts a duration in seconds to decimal hours
	 * @param int $duration the duration in seconds
	 * @return float the duration in decimal hours (rounded to 2 decimals)
	 */
	public static function convertToNbDecimalHours(int $duration): float {
		return round($duration / 3600, 2);
	}

	/**
	 * Converts a duration in seconds to decimal minutes
	 * @param int $duration the duration in seconds
	 * @return float the duration in decimal minutes (rounded to 2 decimals)
	 */
	public static function convertToNbDecimalMinutes(int $duration): float {
		return round($duration / 60, 2);
	}

	// ========== Rounding ==========

	/**
	 * Rounds a duration to a specified precision in minutes
	 * @param int $duration the duration in seconds
	 * @param int $precision the rounding precision in minutes (e.g., 5 for 5-minute increments)
	 * @param string|null $mode the rounding mode: 'up', 'down', or 'close' (default: 'close')
	 * @return int the rounded duration in seconds
	 */
	public static function round(int $duration, int $precision, ?string $mode=null): int
	{
		if ($precision <= 0) {
			// No rounding
			return $duration;
		}

		$mode = mb_strtolower(!empty($mode)?$mode:'close');

		$remainingDuration = $duration;
		$hours = (int) floor($remainingDuration / 3600);
		$remainingDuration -= $hours * 3600;
		$minutes = (int) floor($remainingDuration / 60);
		$remainingDuration -= $minutes * 60;
		$seconds = $remainingDuration % 60;

		$minutesRemaining = $minutes % $precision;
		$minutesRemainingAndSecondsAsDecimal = $minutesRemaining + $seconds/60;
		if ($minutesRemainingAndSecondsAsDecimal === 0) {
			// No rounding needed
			return $duration;
		}

		$halfRoundPrecision = $precision / 2;
		$hoursRounded = $hours;
		$secondsRounded = 0;
		if ($mode === 'up' || ($mode === 'close' && $minutesRemainingAndSecondsAsDecimal > $halfRoundPrecision)) {
			// Round up
			if ($minutes > (60-$precision)) {
				$minutesRounded = 0;
				$hoursRounded++;
			}
			else {
				$minutesRounded = ($minutes-$minutesRemaining)+$precision;
			}
		}
		else {
			// Round down
			$minutesRounded = ($minutes-$minutesRemaining);
		}
		
		return $hoursRounded * 3600 + $minutesRounded * 60 + $secondsRounded;
	}


	// ========== Min/Max Validation ==========

	/**
	 * Checks if a duration is within specified min and max bounds
	 * @param int $duration the duration to check (in seconds)
	 * @param int $durationMin the minimum allowed duration (in seconds)
	 * @param int $durationMax the maximum allowed duration (in seconds)
	 * @return bool true if within bounds, false otherwise
	 */
	public static function checkMinAndMax(int $duration, int $durationMin, int $durationMax): bool
	{
		if ($durationMin > 0 && $durationMax > 0) {
			return ($duration >= $durationMin && $duration <= $durationMax);
		}
		if ($durationMin > 0) {
			return ($duration >= $durationMin);
		}
		if ($durationMax > 0) {
			return ($duration <= $durationMax);
		}
		return false;
	}








	// ========== DEPRECATED METHODS (Backward Compatibility) ==========

	/**
	 * @deprecated use isValid instead
	 */
	public static function check(mixed $enteredDuration, string $separator=':', int $hourPos=1, int $minutePos=2, int $secondPos=3): bool
	{
		return self::isValid($enteredDuration, $separator, $hourPos, $minutePos, $secondPos);
	}

	/**
	 * @deprecated use formatNbHours instead
	 */
	public static function formatHourChrono(int $durationInSeconds, string $displayMode='standard', bool $withSeconds=true): string
	{
		$enumDisplayMode = DurationDisplayMode::parse($displayMode) ?? DurationDisplayMode::STANDARD;
		return self::formatNbHours($durationInSeconds, $enumDisplayMode, $withSeconds);
	}

	/**
	 * @deprecated use formatNbMinutes instead
	 */
	public static function formatMinuteChrono(int $durationInSeconds, string $displayMode='standard'): string
	{
		$enumDisplayMode = DurationDisplayMode::parse($displayMode) ?? DurationDisplayMode::STANDARD;
		return self::formatNbMinutes($durationInSeconds, $enumDisplayMode);
	}

	/**
	 * @deprecated use getIntersectionDurationBetweenTimestampSlots instead
	 */
	public static function getDurationOfIntersectionBetweenTwoTimeSlot(int $timeSlot1StartTimestamp, int $timeSlotEnd1Timestamp, int $timeSlot2StartTimestamp, int $timeSlotEnd2Timestamp): int
	{
		return self::getIntersectionDurationBetweenTimestampSlots($timeSlot1StartTimestamp, $timeSlotEnd1Timestamp, $timeSlot2StartTimestamp, $timeSlotEnd2Timestamp);
	}

}