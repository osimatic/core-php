<?php

namespace Osimatic\Number;

/**
 * Class Number
 * Provides utilities for number formatting, validation, parsing, and mathematical operations
 */
class Number
{
	// ========== Display ==========

	/**
	 * Adds leading zeros to a number to reach a minimum number of digits
	 * @param float|int $number the number to pad
	 * @param int $nbDigitMin the minimum number of digits
	 * @return string the padded number as a string
	 */
	public static function addLeadingZero(float|int $number, int $nbDigitMin): string
	{
		return str_pad($number, $nbDigitMin, '0', STR_PAD_LEFT);
	}

	/**
	 * Formats a number using locale-specific formatting with specified decimal places
	 * @param float|int $number the number to format
	 * @param int $decimals the number of decimal places (default: 2)
	 * @return string the formatted number string
	 */
	public static function format(float|int $number, int $decimals=2): string
	{
		$fmt = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::DECIMAL);
		$fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);
		return \Osimatic\Text\Str::removeNonBreakingSpaces($fmt->format($number));
	}

	/**
	 * Formats a number as an integer (no decimal places)
	 * @param float|int $number the number to format
	 * @return string the formatted integer string
	 */
	public static function formatInt(float|int $number): string
	{
		return self::format($number, 0);
	}

	/**
	 * Formats a number as an ordinal (e.g., 1st, 2nd, 3rd)
	 * @param float|int $number the number to format
	 * @return string the formatted ordinal string
	 */
	public static function formatOrdinal(float|int $number): string
	{
		$fmt = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::ORDINAL);
		return \Osimatic\Text\Str::removeNonBreakingSpaces($fmt->format($number));
	}

	/**
	 * Formats a number in scientific notation
	 * @param float|int $number the number to format
	 * @param int $decimals the number of decimal places (default: 2)
	 * @return string the formatted scientific notation string
	 */
	public static function formatScientific(float|int $number, int $decimals=2): string
	{
		$fmt = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::SCIENTIFIC);
		$fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);
		return \Osimatic\Text\Str::removeNonBreakingSpaces($fmt->format($number));
	}

	/**
	 * Formats a number as spelled out words (e.g., "twenty-three")
	 * @param float|int $number the number to format
	 * @param int $decimals the number of decimal places (default: 2)
	 * @return string the formatted spelled-out string
	 */
	public static function formatSpellOut(float|int $number, int $decimals=2): string
	{
		$fmt = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::SPELLOUT);
		$fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);
		return \Osimatic\Text\Str::removeNonBreakingSpaces($fmt->format($number));
	}

	/**
	 * Formats a binary string for display as hex bytes
	 * @param string $hex the binary string to format
	 * @return string the formatted hex bytes string
	 * @author paladin
	 */
	public static function formatHex(string $hex): string
	{
		$ar = unpack('C*', $hex);
		$str = '';
		foreach ($ar as $v) {
			$s = dechex($v);
			if (strlen($s)<2) {
				$s = "0$s";
			}
			$str .= $s.' ';
		}
		return $str;
	}

	// ========== Parsing and Validation ==========

	/**
	 * Parses a string to a float value
	 * @param string|null $str the string to parse
	 * @return float the parsed float value
	 */
	public static function parseFloat(?string $str): float
	{
		return (float) self::clean($str);
	}

	/**
	 * Parses a string to an integer value
	 * @param string|null $str the string to parse
	 * @return int the parsed integer value
	 */
	public static function parseInt(?string $str): int
	{
		return (int) self::clean($str);
	}

	/**
	 * Parses a non-negative decimal string (dot or comma separator) into an integer number of minor units, without any float conversion
	 * @param string $str the decimal string to parse, e.g. "50,00", "50.5" or "50"
	 * @param int $decimals the number of decimal digits of the minor unit (default: 2, e.g. cents)
	 * @return int|null the amount in minor units, or null if the string is not a valid non-negative decimal
	 */
	public static function parseMinorUnits(string $str, int $decimals=2): ?int
	{
		if ($decimals < 1 || !preg_match('/^(\d+)(?:[.,](\d{1,'.$decimals.'}))?$/', trim($str), $matches)) {
			return null;
		}

		$digits = ltrim($matches[1].str_pad($matches[2] ?? '', $decimals, '0'), '0') ?: '0';
		if (strlen($digits) > 18) {
			return null;
		}
		return (int) $digits;
	}

	/**
	 * Converts a float to a string representation with at least one decimal place
	 * @param float|null $str the float value to convert
	 * @return string the string representation of the float
	 */
	public static function floatToString(?float $str): string
	{
		if ($str === null) {
			return '0.0';
		}
		$result = (string) $str;
		if (!str_contains($result, '.')) {
			$result .= '.0';
		}
		return $result;
	}

	/**
	 * Validates if a string represents a valid float number
	 * @param mixed $str the value to check
	 * @param bool $negativeAllowed whether negative values are allowed (default: true)
	 * @param bool $positiveAllowed whether positive values are allowed (default: true)
	 * @return bool true if valid float, false otherwise
	 */
	public static function isValidFloat(mixed $str, bool $negativeAllowed=true, bool $positiveAllowed=true): bool
	{
		// Check for multiple decimal separators before cleaning
		$strCheck = str_replace([' ', ','], [' ', '.'], (string) $str);
		if (substr_count($strCheck, '.') > 1) {
			return false;
		}

		$str = self::clean((string) $str);

		if (false === self::isValid($str, $negativeAllowed, $positiveAllowed)) {
			return false;
		}

		// Commented out to also allow integer input
		//if (strpos($str, '.') === false) {
		//	return false;
		//}

		return true;
	}

	/**
	 * Validates if a string represents a valid integer number
	 * @param mixed $str the value to check
	 * @param bool $negativeAllowed whether negative values are allowed (default: true)
	 * @param bool $positiveAllowed whether positive values are allowed (default: true)
	 * @return bool true if valid integer, false otherwise
	 */
	public static function isValidInt(mixed $str, bool $negativeAllowed=true, bool $positiveAllowed=true): bool
	{
		$str = self::clean((string) $str, false);

		if (false === self::isValid($str, $negativeAllowed, $positiveAllowed)) {
			return false;
		}

		if (str_contains($str, '.')) {
			return false;
		}

		return true;
	}

	/**
	 * Internal validation for numeric strings
	 * @param mixed $str the value to check
	 * @param bool $negativeAllowed whether negative values are allowed
	 * @param bool $positiveAllowed whether positive values are allowed
	 * @return bool true if valid, false otherwise
	 */
	private static function isValid(mixed $str, bool $negativeAllowed=true, bool $positiveAllowed=true): bool
	{
		// Negative not allowed
		if (false === $negativeAllowed && str_contains($str, '-')) {
			return false;
		}

		// Positive not allowed
		if (false === $positiveAllowed && !str_contains($str, '-')) {
			return false;
		}

		$str = str_replace('.', '', $str);
		if (str_starts_with($str, '-')) {
			$str = substr($str, 1);
		}

		return ctype_digit($str);
	}

	/**
	 * Cleans and normalizes a numeric string
	 * @param string|null $str the string to clean
	 * @param bool $addDecimalIfNotPresent whether to add .0 if no decimal point (default: true)
	 * @return string the cleaned numeric string
	 */
	private static function clean(?string $str, bool $addDecimalIfNotPresent=true): string
	{
		if ($str === null || $str === '') {
			return '0';
		}

		$str = trim($str);
		if (str_starts_with($str, '+')) {
			$str = substr($str, 1);
		}
		$str = str_replace(' ', '', $str);

		// Format comma as decimal separator
		$str = str_replace(',', '.', $str);
		if ($addDecimalIfNotPresent && substr_count($str, '.') === 0) {
			$str .= '.0';
		}

		return $str;
	}


	// ========== Number Rounding ==========

	/**
	 * Rounds a float up to the next higher value
	 * @param float $number the float to round
	 * @param int $precision the number of decimal places to keep (default: 2)
	 * @return float the rounded up float value
	 * @link http://fr2.php.net/manual/fr/function.pow.php
	 * @link http://fr2.php.net/manual/fr/function.ceil.php
	 */
	public static function floatRoundUp(float $number, int $precision=2): float
	{
		if (self::getNbDigitsOfInt(self::decimalPart($number)) === $precision) {
			return $number;
		}

		$multiplier = pow(10, $precision);
		return ceil($multiplier * $number)/$multiplier;
	}

	/**
	 * Rounds a float down to the next lower value
	 * @param float $number the float to round
	 * @param int $precision the number of decimal places to keep (default: 2)
	 * @return float the rounded down float value
	 * @link http://fr2.php.net/manual/fr/function.pow.php
	 * @link http://fr2.php.net/manual/fr/function.floor.php
	 */
	public static function floatRoundDown(float $number, int $precision=2): float
	{
		if (self::getNbDigitsOfInt(self::decimalPart($number)) === $precision) {
			return $number;
		}

		$multiplier = pow(10, $precision);
		return floor($multiplier * $number)/$multiplier;
	}

	// ========== Number Type and Composition ==========

	/**
	 * Checks if a value is an integer (has no decimal part)
	 * @param float|int $val the value to check
	 * @return bool true if integer, false if has decimal part
	 */
	public static function isInteger(float|int $val): bool
	{
		return fmod($val, 1) === 0.0;
	}

	/**
	 * Checks if a value has a decimal part (is not an integer)
	 * @param float|int $val the value to check
	 * @return bool true if has decimal part, false if integer
	 */
	public static function isFloat(float|int $val): bool
	{
		return fmod($val, 1) !== 0.0;
	}

	/**
	 * Calculates the number of digits in an integer
	 * @example this function returns 6 for the number 112233
	 * @param int $int the integer to count digits for
	 * @return int the number of digits in the integer
	 */
	public static function getNbDigitsOfInt(int $int): int
	{
		return strlen((string) (int) $int);
	}

	// ========== Mathematics ==========

	/**
	 * Gets the decimal part of a number as a float
	 * @example this function returns 0.3344 for the number 1122.3344
	 * @param float $float the number to extract the decimal part from
	 * @return float the decimal part as a float
	 */
	public static function decimal(float $float): float
	{
		if (!self::isFloat($float)) {
			return 0;
		}

		$whole = floor($float);
		return $float - $whole;
	}

	/**
	 * Gets the decimal part of a number as an integer
	 * @example this function returns 3344 for the number 1122.3344
	 * @param float $float the number to extract the decimal part from
	 * @return int the decimal part as an integer
	 */
	public static function decimalPart(float $float): int
	{
		if (!self::isFloat($float)) {
			return 0;
		}

		// Use string representation to avoid float subtraction precision issues
		$parts = explode('.', (string) abs($float));
		$decimalStr = rtrim($parts[1] ?? '', '0');

		return empty($decimalStr) ? 0 : (int) $decimalStr;
	}

	/**
	 * Validates a number using the Luhn algorithm (checksum validation)
	 * @param float|int $number the number to validate
	 * @return bool true if valid according to Luhn algorithm, false otherwise
	 */
	public static function checkLuhn(float|int $number): bool
	{
		// Reject 0 and negative numbers
		if ($number <= 0) {
			return false;
		}

		$sum = 0;
		$strNumber = (string) $number;
		$nbDigits = strlen($strNumber);
		$parity = $nbDigits%2;

		for ($i=($nbDigits-1); $i>=0; $i--) {
			$digit = $strNumber[$i];

			if ($i%2 == $parity) {
				$digit = $digit * 2;
			}
			if ($digit > 9) {
				$digit -= 9;
			}
			$sum += $digit;
		}
		return ($sum % 10) === 0;
	}

	// ========== Random Number Generation ==========

	/**
	 * Generates a random integer between min and max (inclusive)
	 * @param int $min the minimum value
	 * @param int $max the maximum value
	 * @return int|false the generated integer, false if an error occurs
	 */
	public static function getRandomInt(int $min, int $max): int|false
	{
		if ($min > $max) {
			return false;
		}

		try {
			return random_int($min, $max);
		} catch (\Exception) {}
		return false;
	}

	/**
	 * Generates a random float between min and max
	 * @param float $min the minimum value
	 * @param float $max the maximum value
	 * @param int $round the number of decimal places to round to (default: 0)
	 * @return float|false the generated float, false if an error occurs
	 */
	public static function getRandomFloat(float $min, float $max, int $round=0): float|false
	{
		if ($min > $max) {
			return false;
		}

		$randomFloat = $min + mt_rand() / mt_getrandmax() * ($max - $min);
		if ($round > 0) {
			$randomFloat = round($randomFloat, $round);
		}
		return $randomFloat;
	}

	// ========== DEPRECATED METHODS (Backward Compatibility) ==========

	/**
	 * @deprecated use isValidFloat instead
	 */
	public static function checkFloat(mixed $str, bool $negativeAllowed=true, bool $positiveAllowed=true): bool
	{
		return self::isValidFloat($str, $negativeAllowed, $positiveAllowed);
	}

	/**
	 * @deprecated use isValidInt instead
	 */
	public static function checkInt(mixed $str, bool $negativeAllowed=true, bool $positiveAllowed=true): bool
	{
		return self::isValidInt($str, $negativeAllowed, $positiveAllowed);
	}
}