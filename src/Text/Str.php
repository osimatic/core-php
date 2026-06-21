<?php

namespace Osimatic\Text;

use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class Str
{

	// ========== Character Replacement ==========

	/**
	 * Replaces a series of characters with other characters in a string.
	 * @param string $str The string in which to replace characters
	 * @param array $replacements List of characters to replace (case-sensitive): key is the character to replace, value is the replacement character
	 * @param boolean $replaceUppercaseChar True to also replace uppercase characters, false otherwise (default: false)
	 * @param boolean $replaceLowercaseChar True to also replace lowercase characters, false otherwise (default: false)
	 * @param boolean $replaceBrutChar True to replace characters as-is, false otherwise (default: true)
	 * @return string The string with replaced characters
	 */
	public static function replaceListChar(string $str, array $replacements, bool $replaceUppercaseChar=false, bool $replaceLowercaseChar=false, bool $replaceBrutChar=true): string
	{
		if ($replaceBrutChar) {
			$str = str_replace(array_keys($replacements), array_values($replacements), $str);
		}
		if ($replaceLowercaseChar) {
			$str = str_replace(array_map(mb_strtolower(...), array_keys($replacements)), array_map(mb_strtolower(...), array_values($replacements)), $str);
		}
		if ($replaceUppercaseChar) {
			// Probleme de conversion des caracteres šž du à la fonction replaceListChar (cf. test StringTest::testSupprimerDiacritiques)
			// mb_strtoupper ne met pas en majuscule ces deux caracteres, ce qui entraine leur remplacement par s et z
			$str = str_replace(array_map(mb_strtoupper(...), array_keys($replacements)), array_map(mb_strtoupper(...), array_values($replacements)), $str);
			// $str = str_replace(mb_strtoupper($listeRemplacementChar), mb_strtoupper($lettreRemplacement), $str);
		}
		return $str;
	}

	/**
	 * Removes a series of characters from a string.
	 * @param string $str The string from which to remove characters
	 * @param array $charactersToRemove List of characters to remove (case-sensitive)
	 * @param boolean $replaceUppercaseChar True to also remove uppercase characters, false otherwise (default: false)
	 * @param boolean $replaceLowercaseChar True to also remove lowercase characters, false otherwise (default: false)
	 * @param boolean $replaceBrutChar True to remove characters as-is, false otherwise (default: true)
	 * @return string The string with removed characters
	 */
	public static function removeListeChar(string $str, array $charactersToRemove, bool $replaceUppercaseChar=false, bool $replaceLowercaseChar=false, bool $replaceBrutChar=true): string
	{
		if ($replaceBrutChar) {
			$str = str_replace($charactersToRemove, '', $str);
		}
		if ($replaceLowercaseChar) {
			$str = str_replace(array_map(mb_strtolower(...), array_values($charactersToRemove)), '', $str);
		}
		if ($replaceUppercaseChar) {
			$str = str_replace(array_map(mb_strtoupper(...), array_values($charactersToRemove)), '', $str);
		}
		return $str;
	}

	// ========== Unicode Normalization ==========

	/**
	 * Sanitizes a string by cleaning problematic Unicode characters and normalizing diacritical marks.
	 * Processing steps:
	 * 1. Replaces problematic typographic characters (curly quotes, dashes, special spaces) with ASCII equivalents via replaceAnnoyingChar()
	 * 2. Normalizes to Unicode NFC (composed) form, composing combining characters with their base characters
	 * 3. Removes any remaining combining diacritical marks that don't compose
	 * Requires the intl PHP extension for proper Unicode normalization.
	 * Without intl, only the combining marks removal is performed after replaceAnnoyingChar().
	 * Example: '"café"' (with curly quotes) becomes '"café"' (with straight quotes)
	 * Example: "e\u{0301}" (e + combining acute) becomes "é" (precomposed)
	 * @param string $input The string to sanitize
	 * @return string The sanitized string with problematic characters replaced and combining marks removed
	 * @link https://www.php.net/manual/en/class.normalizer.php Normalizer class
	 * @link https://unicode.org/reports/tr15/ Unicode Normalization Forms
	 */
	public static function sanitizeInput(string $input): string
	{
		// 1. Nettoyage des caractères typographiques problématiques
		$input = self::replaceAnnoyingChar($input);

		// 2. Normalisation des caractères de combinaison
		if (extension_loaded('intl') && class_exists('Normalizer')) {
			$normalized = \Normalizer::normalize($input, \Normalizer::FORM_C);
		} else {
			$normalized = $input;
		}
		return preg_replace('/\p{Mn}/u', '', $normalized);
	}

	// ========== String Comparison ==========

	/**
	 * Calculates the Levenshtein distance between two strings.
	 * The Levenshtein distance measures the similarity between two strings. It equals the minimum number of characters that must be deleted, inserted, or replaced to transform one string into the other.
	 * @param string $str1 One of the strings to evaluate
	 * @param string $str2 The other string to evaluate
	 * @return int The Levenshtein distance between the two strings, or -1 if either argument contains more than 255 characters
	 * @link https://en.wikipedia.org/wiki/Levenshtein_distance
	 * @link https://www.php.net/manual/en/function.levenshtein.php
	 */
	public static function levenshtein(string $str1, string $str2): int
	{
		return levenshtein($str1, $str2);
	}

	/**
	 * Suggests a similar word from a dictionary based on a given input word.
	 * @param string $word The base word to compare against the dictionary
	 * @param array $dictionnary The list of "allowed" words that can be returned if one is similar to the base word
	 * @param int $distanceMax Maximum desired Levenshtein distance. The higher the maximum distance, the more distant (different) the suggested words will be
	 * @return string|null The closest dictionary word to the base word (i.e., the least "distant" word in the dictionary), or null if no dictionary word matches the distance criterion
	 * @author Jay Salvat (blog.jaysalvat.com)
	 * @link http://blog.jaysalvat.com/article/suggerez-des-orthographes-alternatives-en-php
	 */
	public static function suggest(string $word, array $dictionnary, int $distanceMax = 3): ?string
	{
		$scores = array();
		foreach ($dictionnary as $index) {
			$distanceLevenshtein = levenshtein($word, $index);
			if ($distanceLevenshtein >= 0) {
				$scores[$index] = $distanceLevenshtein;
			}
		}
		$min = min($scores);
		if ($min <= $distanceMax) {
			return array_search($min, $scores, true);
		}
		return null;
	}

	/**
	 * Compares two values (numeric or string) with options for natural ordering and case sensitivity.
	 * @param mixed $val1 The first value to compare
	 * @param mixed $val2 The second value to compare
	 * @param bool $naturalOrder If true, use natural order comparison (default: false)
	 * @param bool $caseSensitive If true, perform case-sensitive comparison (default: false)
	 * @return int Returns -1 if $val1 < $val2, 1 if $val1 > $val2, or 0 if they are equal
	 */
	public static function compare(mixed $val1, mixed $val2, bool $naturalOrder=false, bool $caseSensitive=false): int
	{
		if (is_numeric($val1) && is_numeric($val2)) {
			if ($val1 < $val2) {
				return -1;
			}
			if ($val1 > $val2) {
				return 1;
			}
			return 0;
		}

		if ($naturalOrder) {
			if ($caseSensitive) {
				return strnatcmp($val1, $val2); // Natural order comparison, case-sensitive
			}
			return strnatcasecmp($val1, $val2); // Natural order comparison, case-insensitive
		}

		if ($caseSensitive) {
			return strcmp($val1, $val2); // Comparison, case-sensitive
		}
		return strcasecmp($val1, $val2); // Comparison, case-insensitive
	}

	// ========== String Truncation ==========

	/**
	 * Truncates a string from the beginning before a certain number of characters and optionally adds a string before the truncation.
	 * @param string $string The string to truncate
	 * @param int $nbCharMax The maximum number of characters in the string (truncating from the beginning)
	 * @param bool $dontCutInMiddleOfWord True to avoid cutting in the middle of a word (wait for word end), false to cut strictly at the maximum character count (default: true)
	 * @param string $strAddingAtBeginning The string to add after truncation, if truncation occurred (default: "…")
	 * @return string The truncated string
	 */
	public static function truncateTextAtBeginning(string $string, int $nbCharMax, bool $dontCutInMiddleOfWord = true, string $strAddingAtBeginning = '…'): string
	{
		$space = ' ';
		$stringTruncate = $string;

		if (mb_strlen($string) > $nbCharMax) {
			$ellipsisLength = mb_strlen($strAddingAtBeginning);
			$maxTextLength = $nbCharMax - $ellipsisLength;
			$stringTruncate = mb_substr($string, -$maxTextLength);

			if ($dontCutInMiddleOfWord && $stringTruncate[0] !== $space) {
				$posSpace = strpos($stringTruncate, $space);
				if ($posSpace !== false) {
					$stringTruncate = mb_substr($stringTruncate, $posSpace);
				}
			}

			$stringTruncate = $strAddingAtBeginning.$stringTruncate;
		}

		return $stringTruncate;
	}

	/**
	 * Truncates a string after a certain number of characters and optionally adds a string after the truncation.
	 * @param string $string The string to truncate
	 * @param int $nbCharMax The maximum number of characters in the string (truncating from the end)
	 * @param bool $dontCutInMiddleOfWord True to avoid cutting in the middle of a word (wait for word end), false to cut strictly at the maximum character count (default: true)
	 * @param string $strAddingAtEnd The string to add after truncation, if truncation occurred (default: "…")
	 * @return string The truncated string
	 */
	public static function truncateTextAtEnd(string $string, int $nbCharMax, bool $dontCutInMiddleOfWord = true, string $strAddingAtEnd = '…'): string
	{
		$space = ' ';
		$stringTruncate = $string;

		if (mb_strlen($string) > $nbCharMax) {
			$ellipsisLength = mb_strlen($strAddingAtEnd);
			$maxTextLength = $nbCharMax - $ellipsisLength;
			$stringTruncate = mb_substr($string, 0, $maxTextLength);

			if ($dontCutInMiddleOfWord && mb_strlen($string) > $maxTextLength && $string[$maxTextLength] !== $space) {
				$posSpace = mb_strrpos($stringTruncate, $space);
				if ($posSpace !== false) {
					$stringTruncate = mb_substr($stringTruncate, 0, $posSpace);
				}
			}

			$stringTruncate .= $strAddingAtEnd;
		}
		return $stringTruncate;
	}

	/**
	 * Truncates a string in the middle, keeping the beginning and end.
	 * @param string $string The string to truncate
	 * @param int $nbCharMax The maximum number of characters in the result
	 * @param bool $dontCutInMiddleOfWord If true, avoid cutting in the middle of words
	 * @param string $strAddingInMiddle The string to add in the middle where text was removed (default: "[…]")
	 * @return string The truncated string
	 */
	public static function truncateTextInMiddle(string $string, int $nbCharMax, bool $dontCutInMiddleOfWord = true, string $strAddingInMiddle = '[…]'): string
	{
		$stringTruncate = $string;
		$space = ' ';

		if (mb_strlen($string) > $nbCharMax) {
			$ellipsisLength = mb_strlen($strAddingInMiddle);
			$availableLength = $nbCharMax - $ellipsisLength;
			$nbCharEachPart = (int)($availableLength / 2);

			$beginPart = mb_substr($string, 0, $nbCharEachPart);
			$endPart = mb_substr($string, -$nbCharEachPart);

			if ($dontCutInMiddleOfWord) {
				// Adjust beginning part to not cut in middle of word
				if ($nbCharEachPart < mb_strlen($string) && $string[$nbCharEachPart] !== $space) {
					$posSpace = mb_strrpos($beginPart, $space);
					if ($posSpace !== false) {
						$beginPart = mb_substr($beginPart, 0, $posSpace);
					}
				}

				// Adjust end part to not cut in middle of word
				if ($endPart[0] !== $space) {
					$posSpace = mb_strpos($endPart, $space);
					if ($posSpace !== false) {
						$endPart = mb_substr($endPart, $posSpace + 1);
					}
				}
			}

			$stringTruncate = $beginPart . $strAddingInMiddle . $endPart;
		}

		return $stringTruncate;
	}

	/**
	 * This function will strip tags from a string, split it at its max_length and ellipsize
	 * @param string $str string to ellipsize
	 * @param int $nbCharInFinalString max length of string
	 * @param int|float $whereEllipsisShouldAppear int (1|0) or float, .5, .2, etc. for position to split
	 * @param string $ellipsis ellipsis ; Default '…'
	 * @return string ellipsized string
	 */
	public static function ellipsize(string $str, int $nbCharInFinalString, int|float $whereEllipsisShouldAppear=1, string $ellipsis = '&hellip;'): string
	{
		// Strip tags
		$str = trim(strip_tags($str));

		// Is the string long enough to ellipsize?
		if (strlen($str) <= $nbCharInFinalString) {
			return $str;
		}

		$ellipsisLength = strlen($ellipsis);
		$availableLength = $nbCharInFinalString - $ellipsisLength;

		$beg = substr($str, 0, floor($availableLength * $whereEllipsisShouldAppear));

		$whereEllipsisShouldAppear = ($whereEllipsisShouldAppear > 1) ? 1 : $whereEllipsisShouldAppear;
		if ($whereEllipsisShouldAppear === 1) {
			$end = substr($str, 0, -($availableLength - strlen($beg)));
		}
		else {
			$end = substr($str, -($availableLength - strlen($beg)));
		}

		return $beg.$ellipsis.$end;
	}


	// ========== Validation ==========

	/**
	 * Checks if a string contains only lowercase characters.
	 * @param string $string The string to check
	 * @param bool $numericAllowed If true, allows numeric characters in the string
	 * @return bool True if the string is all lowercase, false otherwise
	 */
	public static function isLowercase(string $string, bool $numericAllowed=true): bool
	{
		if ($numericAllowed) {
			$string = preg_replace('/\d/', '', $string);
		}
		return ctype_lower($string);
	}

	/**
	 * Checks if a string contains only uppercase characters.
	 * @param string $string The string to check
	 * @param bool $numericAllowed If true, allows numeric characters in the string
	 * @return bool True if the string is all uppercase, false otherwise
	 */
	public static function isUppercase(string $string, bool $numericAllowed=true): bool
	{
		if ($numericAllowed) {
			$string = preg_replace('/\d/', '', $string);
		}
		return ctype_upper($string);
	}

	/**
	 * Checks if a string's length is within a specified range.
	 * @param string $string The string to check
	 * @param int $nbCharMin The minimum number of characters allowed
	 * @param int $nbCharMax The maximum number of characters allowed
	 * @return bool True if the string length is within range, false otherwise
	 */
	public static function hasLengthBetween(string $string, int $nbCharMin, int $nbCharMax): bool
	{
		if ($nbCharMin > $nbCharMax) {
			return false;
		}

		if (!preg_match('#^(.){'.($nbCharMin===$nbCharMax?$nbCharMin:$nbCharMin.','.$nbCharMax).'}$#', $string)) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if a string contains only alphabetic characters and has a length within the specified range.
	 * @param string $string The string to check
	 * @param int $nbCharMin The minimum number of characters allowed
	 * @param int $nbCharMax The maximum number of characters allowed
	 * @return bool True if the string is all alphabetic and within length range, false otherwise
	 */
	public static function isAlphabeticWithLength(string $string, int $nbCharMin, int $nbCharMax): bool
	{
		if (!ctype_alpha($string)) {
			return false;
		}

		return self::hasLengthBetween($string, $nbCharMin, $nbCharMax);
	}

	/**
	 * Checks if a string contains only alphanumeric characters and has a length within the specified range.
	 * @param string $string The string to check
	 * @param int $nbCharMin The minimum number of characters allowed
	 * @param int $nbCharMax The maximum number of characters allowed
	 * @return bool True if the string is all alphanumeric and within length range, false otherwise
	 */
	public static function isAlphanumericWithLength(string $string, int $nbCharMin, int $nbCharMax): bool
	{
		if (!ctype_alnum($string)) {
			return false;
		}

		return self::hasLengthBetween($string, $nbCharMin, $nbCharMax);
	}

	/**
	 * Checks if a string contains only numeric characters and has a length within the specified range.
	 * @param string $string The string to check
	 * @param int $nbCharMin The minimum number of characters allowed
	 * @param int $nbCharMax The maximum number of characters allowed
	 * @param bool $canStartWithZero If false, the string cannot start with '0' (default: true)
	 * @return bool True if the string is all numeric and within length range, false otherwise
	 */
	public static function isNumericWithLength(string $string, int $nbCharMin, int $nbCharMax, bool $canStartWithZero=true): bool
	{
		if (!ctype_digit($string)) {
			return false;
		}

		if (false === $canStartWithZero && $string !== '' && $string[0] === '0') {
			return false;
		}

		return self::hasLengthBetween($string, $nbCharMin, $nbCharMax);
	}

	/**
	 * Checks if a string contains both alphabetic and numeric characters (but not only numeric).
	 * @param string $string The string to check
	 * @return bool True if the string is alphanumeric and contains at least one letter, false otherwise
	 */
	public static function ctype_alpha_and_num(string $string): bool
	{
		return ctype_alnum($string) && !ctype_digit($string);
	}

	// ========== Character Counting ==========

	/**
	 * Pads a string with spaces to reach a specified length.
	 * @param string $string The string to pad
	 * @param int $nbCharFormat The desired total length
	 * @param bool $addBlankInBeginning If true, add spaces at the beginning; otherwise add at the end (default: false)
	 * @return string The padded string
	 */
	public static function getStringWithBlank(string $string, int $nbCharFormat, bool $addBlankInBeginning=false): string
	{
		$nbBlankAdd = $nbCharFormat - strlen($string);
		$strBlank = self::getStringWithSameChar(' ', $nbBlankAdd);
		if ($addBlankInBeginning) {
			$stringFormat = $strBlank.$string;
		}
		else {
			$stringFormat = $string.$strBlank;
		}
		return $stringFormat;
	}

	/**
	 * Creates a string by repeating the same character multiple times.
	 * @param string $char The character to repeat
	 * @param int $nb The number of times to repeat the character
	 * @return string The resulting string
	 */
	public static function getStringWithSameChar(string $char, int $nb): string
	{
		return str_repeat($char, $nb);
	}

	/**
	 * Returns the number of occurrences of a specific character in a string.
	 * @param string $str The string to search in
	 * @param string $char The character to count
	 * @return int The number of occurrences
	 */
	public static function getNumberOccurrencesOfPreciseChar(string $str, string $char): int
	{
		return substr_count($str, $char);
	}

	/**
	 * Returns the total number of occurrences of characters from a list in a string.
	 * @param string $str The string to search in
	 * @param array|string $listChar An array or string of characters to count
	 * @return int The total number of occurrences of all specified characters
	 */
	public static function getNumberOccurrencesOfListChar(string $str, array|string $listChar): int
	{
		if (!is_array($listChar)) {
			$stringListeChar = $listChar;
			$listChar = array();
			$strlen = strlen($stringListeChar);
			for ($numChar=0; $numChar<$strlen; $numChar++) {
				$listChar[] = $stringListeChar[$numChar];
			}
		}

		$nbChar = 0;
		foreach ($listChar as $char) {
			$nbChar += substr_count($str, $char);
		}
		return $nbChar;
	}

	/**
	 * Tests if a string contains only the same character repeated.
	 * @param string $str The string to test
	 * @return bool True if the string contains only one unique character, false if it contains at least 2 different characters
	 */
	public static function containsOnlySameChar(string $str): bool
	{
		return strlen(count_chars($str, 3)) === 1;
	}

	/**
	 * Tests if a string contains only different characters (no duplicates).
	 * @param string $str The string to test
	 * @return bool True if all characters are unique, false if any character appears at least twice
	 */
	public static function containsOnlyDifferentChar(string $str): bool
	{
		foreach (count_chars($str, 1) as $val) {
			if ($val > 1) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks if a string contains at least a minimum number of unique characters.
	 * @param string $str The string to check
	 * @param int $min The minimum number of unique characters required (default: 2)
	 * @return bool True if the string contains at least $min unique characters, false otherwise
	 */
	public static function nbCharUniqueMinimum(string $str, int $min=2): bool
	{
		return count(count_chars($str, 1)) >= $min;
	}

	/**
	 * Checks if a string contains at most a maximum number of unique characters.
	 * @param string $str The string to check
	 * @param int $max The maximum number of unique characters allowed (default: 2)
	 * @return bool True if the string contains at most $max unique characters, false otherwise
	 */
	public static function nbCharUniqueMaximum(string $str, int $max=2): bool
	{
		return count(count_chars($str, 1)) <= $max;
	}

	// ========== Transformation ==========

	/**
	 * Handles singular/plural forms in a string based on a number.
	 * Usage: "{No items|1 item|{#} items}"
	 * @param string $string The string to convert to singular or plural form
	 * @param int|float $nb The number of elements that determines whether the string should be singular or plural
	 * @return string The string in singular or plural form
	 * @author Jay Salvat
	 */
	public static function pluralize(string $string, int|float $nb): string
	{
		// Replace {#} with the number
		$string = str_replace('{#}', $nb, $string);
		// Find all occurrences of {...}
		preg_match_all("/\{(.*?)\}/", $string, $matches);
		foreach($matches[1] as $k=>$v) {
			// Split the occurrence at |
			$part = explode('|', $v);
			// If zero
			if ($nb === 0) {
				$mod = (count($part) === 1) ? '' : $part[0];
			}
			// If singular
			else if ($nb === 1) {
				$mod = (count($part) === 1) ? '' : ((count($part) === 2) ? $part[0] : $part[1]);
			}
			// Otherwise plural
			else {
				$mod = (count($part) === 1) ? $part[0] : ((count($part) === 2) ? $part[1] : $part[2]);
			}
			// Replace found occurrences with the correct result
			$string = str_replace($matches[0][$k], $mod , $string);
		}
		return $string;
	}

	/**
	 * Converts a string with spaces to underscore-separated format.
	 * Replaces all whitespace characters with underscores and converts to lowercase.
	 * @param string $str The string to convert (e.g., "Hello World")
	 * @return string The underscored string (e.g., "hello_world")
	 */
	public static function underscore(string $str): string
	{
		return preg_replace('/[\s]+/', '_', mb_strtolower(trim($str)));
	}

	/**
	 * Converts an underscore-separated string to a human-readable format.
	 * Replaces underscores with spaces and capitalizes each word.
	 * @param string $str The string to humanize (e.g., "hello_world")
	 * @return string The humanized string (e.g., "Hello World")
	 */
	public static function humanize(string $str): string
	{
		return ucwords(preg_replace('/[_]+/', ' ', mb_strtolower(trim($str))));
	}

	/**
	 * Converts a string from CamelCase to snake_case.
	 * @param string $str The string to convert
	 * @return string The converted string in snake_case format
	 */
	public static function toSnakeCase(string $str): string
	{
		return (new CamelCaseToSnakeCaseNameConverter())->normalize($str);
	}

	/**
	 * Converts a string from snake_case or space-separated to camelCase.
	 * Capitalizes the first letter of each word except the first one and removes separators.
	 * @param string $str The string to convert (e.g., "hello_world" or "hello world")
	 * @return string The converted string in camelCase format (e.g., "helloWorld")
	 */
	public static function toCamelCase(string $str): string
	{
		return (new CamelCaseToSnakeCaseNameConverter())->denormalize($str);

		//$str = 'x'.mb_strtolower(trim($str));
		//$str = ucwords(preg_replace('/[\s_]+/', ' ', $str));
		//return substr(str_replace(' ', '', $str), 1);
	}

	/**
	 * Removes all whitespace characters (spaces, tabs, newlines) from a string.
	 * @param string $str The string from which to remove spaces
	 * @param string $replace The replacement string for removed spaces (default: empty string)
	 * @return string The string with all whitespace removed or replaced
	 */
	public static function removeSpaces(string $str, string $replace=''): string
	{
		$str = str_replace('&nbsp;', $replace, $str);
		$str = preg_replace('#\s+#', $replace, $str);
		// $str = preg_replace('#([\s\t]*)#', $replace, $str);
		return $str;
	}

	/**
	 * Removes non-breaking spaces from a string and replaces them with regular spaces.
	 * Specifically handles narrow no-break space (U+202F).
	 * @param string $str The string from which to remove non-breaking spaces
	 * @return string The string with non-breaking spaces replaced by regular spaces
	 */
	public static function removeNonBreakingSpaces(string $str): string
	{
		// Remove narrow no-break space (U+202F)
		//$str = preg_replace("\u{00a0}", '', $str);
		//$str = preg_replace("\u{0020}", '', $str);
		return str_replace("\xE2\x80\xAF", ' ', $str);
	}

	/**
	 * Removes all line breaks from a string.
	 * This is an alias for normalizeBreaks() with an empty replacement string.
	 * @param string $str The string from which to remove line breaks
	 * @param string $replace The replacement string for removed line breaks (default: empty string)
	 * @return string The string with all line breaks removed or replaced
	 */
	public static function removeLineBreak(string $str, string $replace=''): string
	{
		return self::normalizeBreaks($str, $replace);
	}

	/**
	 * Normalize line breaks in a string.
	 * Converts UNIX LF, Mac CR and Windows CRLF line breaks into a single line break format.
	 * Defaults to CRLF (for message bodies) and preserves consecutive breaks.
	 * @param string $text The text to normalize
	 * @param string $breakType What kind of line break to use, defaults to CRLF
	 * @return string The string with all line breaks normalized to the specified format
	 */
	public static function normalizeBreaks(string $text, string $breakType = "\r\n"): string
	{
		return preg_replace('/(\r\n|\r|\n)/ms', $breakType, $text);
	}

	/**
	 * Removes all punctuation characters from a string.
	 * Handles common punctuation marks including quotes, brackets, and special characters.
	 * @param string $str The string from which to remove punctuation
	 * @param string $replace The replacement string for removed punctuation (default: empty string)
	 * @return string The string with punctuation removed or replaced, with multiple spaces collapsed to single spaces
	 */
	public static function removePunctuation(string $str, string $replace=''): string
	{
		$charList = [',', '‚', ';', ':', '.', '…', '?', '!', '"', '\'', '(', ')', '[', ']', '{', '}', '‘', '’', '“', '”', '«', '»', '<', '>'];
		foreach ($charList as $char) {
			$str = preg_replace('#\\'.$char.'#', $replace, $str);
		}
		// Clean up double spaces that may be created after punctuation removal
		$str = preg_replace('#\s{2,}#', ' ', $str);
		return $str;
	}

	/**
	 * Removes all emoji characters from a string, including composed sequences
	 * (ZWJ sequences, variation selectors, regional indicator symbols).
	 *
	 * @link https://unicode.org/reports/tr51/ Unicode Emoji specification
	 * @param string $str The input string
	 * @return string The string with all emojis removed
	 */
	public static function removeEmoji(string $str): string
	{
		// Remove extended pictographic (covers most modern emojis)
		$str = preg_replace('/\p{Extended_Pictographic}/u', '', $str);

		// Remove ZWJ (zero-width joiner used in composed emojis like 👨‍👩‍👧)
		$str = preg_replace('/\x{200D}/u', '', $str);

		// Remove variation selectors (e.g., U+FE0F used to render text as emoji)
		$str = preg_replace('/\x{FE0F}/u', '', $str);

		// Remove regional indicator symbols (flag emojis: 🇫🇷, 🇺🇸, etc.)
		$str = preg_replace('/[\x{1F1E0}-\x{1F1FF}]/u', '', $str);

		return $str;
	}

	/**
	 * Replaces special Unicode characters with their ASCII equivalents or removes them.
	 * Handles various Unicode spaces, zero-width characters, special quotation marks, dashes, and other typographic characters.
	 * @param string $str The string to process
	 * @return string The string with special characters replaced by standard ASCII equivalents
	 */
	public static function replaceAnnoyingChar(string $str): string
	{
		$replacements = [
			// spaces
			"\xe2\x80\x80" => ' ', //  	-> En quad
			"\xe2\x80\x81" => ' ', //  	-> Em quad
			"\xe2\x80\x82" => ' ', //  	-> En space
			"\xe2\x80\x83" => ' ', //  	-> Em space
			"\xe2\x80\x84" => ' ', //  	-> Three-per-em space
			"\xe2\x80\x85" => ' ', //  	-> Four-per-em space
			"\xe2\x80\x86" => ' ', //  	-> Six-per-em space
			"\xe2\x80\x87" => ' ', //  	-> Figure space
			"\xe2\x80\x88" => ' ', //  	-> Punctuation space
			"\xe2\x80\x89" => ' ', //  	-> Thin space
			"\xe2\x80\x8a" => ' ', //  	-> Hair space

			// empty
			"\xe2\x80\x8b" => '', // ​ 	-> Zero width space
			"\xe2\x80\x8c" => '', // ‌ 	-> Zero width non-joiner
			"\xe2\x80\x8d" => '', // ‍ 	-> Zero width joiner
			"\xe2\x80\x8e" => '', // ‎ 	-> Left-to-right mark
			"\xe2\x80\x8f" => '', // Right-to-left mark -> ‏

			// hyphen
			"\xe2\x80\x90" => '-', // ‐ -> Hyphen
			"\xe2\x80\x91" => '-', // ‑ -> Non-breaking hyphen
			"\xe2\x80\x92" => '-', // ‒ -> Figure dash
			"\xe2\x80\x93" => '-', // – -> En dash
			"\xe2\x80\x94" => '-', // — -> Em dash
			"\xe2\x80\x95" => '-', // ― -> Horizontal bar

			// others
			"\xe2\x80\x96" => '|', // ‖ 	-> Double vertical line
			"\xe2\x80\x97" => '_', // ‗ 	-> Double low line
			"\xe2\x80\x98" => "'", // ‘		-> Left single quotation mark
			"\xe2\x80\x99" => "'", // ’		-> Right single quotation mark
			"\xe2\x80\x9a" => "'", // ‚		-> Single low-9 quotation mark
			"\xe2\x80\x9b" => "'", // ‛		-> Single high-reversed-9 quotation mark
			"\xe2\x80\x9c" => '"', // “		-> Left double quotation mark
			"\xe2\x80\x9d" => '"', // ”		-> Right double quotation mark
			"\xe2\x80\x9e" => '"', // „		-> Double low-9 quotation mark
			"\xe2\x80\x9f" => '"', // ‟		-> Double high-reversed-9 quotation mark
			"\xe2\x80\xa0" => '', // †		-> Dagger
			"\xe2\x80\xa1" => '', // ‡		-> Double dagger
			"\xe2\x80\xa2" => '.', // •		-> Bullet
			"\xe2\x80\xa3" => '.', // ‣		-> Triangular bullet
			"\xe2\x80\xa4" => '.', // ․		-> One dot leader
			"\xe2\x80\xa5" => '.', // ‥		-> Two dot leader
			"\xe2\x80\xa6" => '...', // …	-> Horizontal ellipsis
			"\xe2\x80\xa7" => '.', // ‧		-> Hyphenation point

			"\xe2\x80\xa8" => ' ', //  	-> Line separator
			"\xe2\x80\xa9" => ' ', //  	-> Paragraph separator
			"\xe2\x80\xaa" => ' ', // ‪	-> Left-to-right embedding
			"\xe2\x80\xab" => ' ', // ‫	-> Right-to-left embedding
			"\xe2\x80\xac" => ' ', // ‬	-> Pop directional formatting
			"\xe2\x80\xad" => ' ', // ‭	-> Left-to-right override
			"\xe2\x80\xae" => ' ', // ‮	-> Right-to-left override
			"\xe2\x80\xaf" => ' ', //  	-> Narrow no-break space

			"\xe2\x80\xb2" => "'", // ′		-> Prime
			"\xe2\x80\xb3" => '"', // ″		-> Double prime
			"\xe2\x80\xb4" => '"', // ‴		-> Triple prime
			"\xe2\x80\xb5" => "'", // ‵		-> Reversed prime
			"\xe2\x80\xb6" => '"', // ‶		-> Reversed double prime
			"\xe2\x80\xb7" => '"', // ‷		-> Reversed triple prime
			"\xe2\x80\xb8" => '', // ‸		-> Caret
			"\xe2\x80\xb9" => '<', // ‹		-> Single left-pointing angle quotation mark
			"\xe2\x80\xba" => '>', // ›		-> Single right-pointing angle quotation mark
			"\xe2\x80\xbb" => '*', // ※	-> Reference mark
			"\xe2\x80\xbc" => '!!', // ‼	-> Double exclamation mark
			"\xe2\x80\xbd" => '', // ‽		-> Interrobang
			"\xe2\x80\xbe" => '', // ‾		-> Overline
			"\xe2\x80\xbf" => '', // ‿		-> Undertie
			"\xe2\x81\x80" => '', // ⁀		-> Character tie
			"\xe2\x81\x81" => '', // ⁁		-> Caret insertion point
			"\xe2\x81\x82" => '*', // ⁂	-> Asterism
			"\xe2\x81\x83" => '-', // ⁃		-> Hyphen bullet
			"\xe2\x81\x84" => '/', // ⁄		-> Fraction slash
			"\xe2\x81\x85" => '', // ⁅		-> Left square bracket with quill
			"\xe2\x81\x86" => '', // ⁆		-> Right square bracket with quill
			"\xe2\x81\x87" => '??', // ⁇	-> Double question mark
			"\xe2\x81\x88" => '?!', // ⁈	-> Question exclamation mark
			"\xe2\x81\x89" => '!?', // ⁉	-> Exclamation question mark
			"\xe2\x81\x8a" => '', // ⁊		-> Tironian sign et
			"\xe2\x81\x8b" => '', // ⁋		-> Reversed pilcrow sign
			"\xe2\x81\x8c" => '.', // ⁌		-> Black leftwards bullet
			"\xe2\x81\x8d" => '.', // ⁍		-> Black rightwards bullet
			"\xe2\x81\x8e" => '*', // ⁎		-> Low asterisk
			"\xe2\x81\x8f" => ';', // ⁏		-> Reversed semicolon
			"\xe2\x81\x90" => '', // ⁐		-> Close up
			"\xe2\x81\x91" => '*', // ⁑		-> Two asterisks aligned vertically
			"\xe2\x81\x92" => '', // ⁒		-> Commercial minus sign
			"\xe2\x81\x93" => '', // ⁓		-> Swung dash
			"\xe2\x81\x94" => '', // ⁔		-> Inverted undertie
			"\xe2\x81\x95" => '', // ⁕		-> Flower punctuation mark
			"\xe2\x81\x96" => '', // ⁖		-> Three dot punctuation
			"\xe2\x81\x97" => '', // ⁗		-> Quadruple prime
			"\xe2\x81\x98" => '', // ⁘		-> Four dot punctuation
			"\xe2\x81\x99" => '', // ⁙		-> Five dot punctuation
			"\xe2\x81\x9a" => '', // ⁚		-> Two dot punctuation
			"\xe2\x81\x9b" => '', // ⁛		-> Four dot mark
			"\xe2\x81\x9c" => '', // ⁜		-> Dotted cross
			"\xe2\x81\x9d" => '', // ⁝		-> Tricolon
			"\xe2\x81\x9e" => '', // ⁞		-> Vertical four dots

			"\xe2\x81\x9f" => ' ', //  	-> Medium mathematical space
			"\xe2\x81\xa0" => '', // ⁠		-> Word joiner
			"\xe2\x81\xa1" => '', // ⁡	-> Function application
			"\xe2\x81\xa2" => '', // ⁢		-> Invisible times
			"\xe2\x81\xa3" => '', // ⁣		-> Invisible separatoR
			"\xe2\x81\xa4" => '', // ⁤		-> Invisible plus
			"\xe2\x81\xa5" => '', // ⁥		->
			"\xe2\x81\xa6" => '', // ⁦	-> Left-to-right isolate
			"\xe2\x81\xa7" => '', // ⁧	-> Right-to-left isolate
			"\xe2\x81\xa8" => '', // ⁨	-> First strong isolate
			"\xe2\x81\xa9" => '', // ⁩	-> Pop directional isolate
			"\xe2\x81\xaa" => '', // ⁪	-> Inhibit symmetric swapping
			"\xe2\x81\xab" => '', // ⁫	-> Activate symmetric swapping
			"\xe2\x81\xac" => '', // ⁬	-> Inhibit arabic form shaping
			"\xe2\x81\xad" => '', // ⁭	-> Activate arabic form shaping
			"\xe2\x81\xae" => '', // ⁮	-> National digit shapes
			"\xe2\x81\xaf" => '', // ⁯	-> Nominal digit shapes
		];

		return str_replace(array_keys($replacements), array_values($replacements), $str);
	}

	/**
	 * Reduces multiple consecutive instances of a particular character to a single instance.
	 * Example: "Fred, Bill,, Joe, Jimmy" becomes "Fred, Bill, Joe, Jimmy"
	 * @param string $str The string to process
	 * @param string $character The character you wish to reduce (default: comma)
	 * @param bool $trim Whether to trim the character from the beginning/end (default: false)
	 * @return string The string with consecutive occurrences of the character reduced to single instances
	 */
	public static function reduceMultiples(string $str, string $character=',', bool $trim=false): string
	{
		$str = preg_replace('#'.preg_quote($character, '#').'{2,}#', $character, $str);
		if ($trim === true) {
			$str = trim($str, $character);
		}
		return $str;
	}

	/**
	 * Adds or increments a numeric suffix to a string for versioning or duplicate handling.
	 * Example: "file" becomes "file_1", "file_1" becomes "file_2", etc.
	 * @param string $str The string to increment
	 * @param string $separator The separator between the string and number (default: underscore)
	 * @param int $first The starting number for first increment (default: 1)
	 * @return string The string with an added or incremented numeric suffix
	 */
	public static function increment(string $str, string $separator = '_', int $first = 1): string
	{
		preg_match('/(.+)'.$separator.'([0-9]+)$/', $str, $match);
		return isset($match[2]) ? $match[1].$separator.(((int) $match[2]) + 1) : $str.$separator.$first;
	}

	/**
	 * Repeats a string a specified number of times.
	 * @param string $data The string to repeat
	 * @param int $num Number of times to repeat the string (default: 1)
	 * @return string The repeated string, or empty string if $num is less than or equal to 0
	 */
	public static function repeater(string $data, int $num = 1): string
	{
		return (($num > 0) ? str_repeat($data, $num) : '');
	}

	/**
	 * Censors disallowed words in a string by replacing them with a specified replacement.
	 * Supports wildcard patterns (e.g., "bad*" matches "bad", "badword", etc.).
	 * @param string $str The text string to censor
	 * @param array $censored The array of words to censor
	 * @param string $replacement The replacement value (default: "####")
	 * @return string The text with censored words replaced by the specified replacement string
	 */
	public static function censorWord(string $str, array $censored, string $replacement='####'): string
	{
		if (empty($censored)) {
			return $str;
		}

		$str = ' '.$str.' ';

		// \w, \b and a few others do not match on a unicode character set for performance reasons. As a result words like über will not match on a word boundary. Instead, we'll assume that a bad word will be bookeneded by any of these characters.
		$delim = '[-_\'\"`(){}<>\[\]|!?@#%&,.:;^~*+=\/ 0-9\n\r\t]';

		foreach ($censored as $badword) {
			$pattern = "/($delim)(".str_replace('\*', '\w*?', preg_quote($badword, '/')).")($delim)/i";
			if ($replacement !== '') {
				$str = preg_replace($pattern, "\\1$replacement\\3", $str);
			}
			else {
				$str = preg_replace_callback($pattern, static fn($matches) => $matches[1] . str_repeat('#', strlen($matches[2])) . $matches[3], $str);
			}
		}
		return trim($str);
	}

	/**
	 * Wraps text at the specified character. Maintains the integrity of words.
	 * Anything placed between {unwrap}{/unwrap} will not be word wrapped, nor will URLs.
	 * @param string $str The text string to wrap
	 * @param int $charlim The number of characters to wrap at
	 * @return string The word-wrapped text with line breaks inserted at appropriate positions
	 */
	public static function wrapWord(string $str, int $charlim): string
	{
		// Se the character limit
		if ( ! is_numeric($charlim)) {
			$charlim = 76;
		}

		// Reduce multiple spaces
		$str = preg_replace('/ +/', ' ', $str);

		// Standardize newlines
		if (str_contains($str, "\r")) {
			$str = str_replace(["\r\n", "\r"], "\n", $str);
		}

		// If the current word is surrounded by {unwrap} tags we'll
		// strip the entire chunk and replace it with a marker.
		$unwrap = [];
		if (preg_match_all("|(\{unwrap\}.+?\{/unwrap\})|s", $str, $matches)) {
			$nb = count($matches['0']);
			for ($i = 0; $i < $nb; $i++) {
				$unwrap[] = $matches['1'][$i];
				$str = str_replace($matches['1'][$i], '{{unwrapped'.$i.'}}', $str);
			}
		}

		// Use PHP's native function to do the initial wordwrap.
		// We set the cut flag to FALSE so that any individual words that are
		// too long get left alone.  In the next step we'll deal with them.
		$str = wordwrap($str, $charlim, "\n", false);

		// Split the string into individual lines of text and cycle through them
		$outputLines = [];
		foreach (explode("\n", $str) as $line) {
			// Is the line within the allowed character count? If so we'll join it to the output and continue
			if (strlen($line) <= $charlim) {
				$outputLines[] = $line;
				continue;
			}

			$temp = '';
			while ((strlen($line)) > $charlim) {
				// If the over-length word is a URL we won't wrap it
				if (preg_match("!\[url.+\]|://|wwww.!", $line)) {
					break;
				}

				// Trim the word down
				$temp .= substr($line, 0, $charlim-1);
				$line = substr($line, $charlim-1);
			}

			// If $temp contains data it means we had to split up an over-length word into smaller chunks so we'll add it back to our current line
			if ($temp !== '') {
				$outputLines[] = $temp;
				$outputLines[] = $line;
			}
			else {
				$outputLines[] = $line;
			}
		}

		$output = implode("\n", $outputLines);

		// Put our markers back
		if (count($unwrap) > 0) {
			foreach ($unwrap as $key => $val) {
				$output = str_replace('{{unwrapped'.$key.'}}', $val, $output);
			}
		}

		// Remove the unwrap tags
		$output = str_replace(['{unwrap}', '{/unwrap}'], '', $output);

		return $output;
	}

	/**
	 * Removes accents from characters and converts them to their ASCII equivalents.
	 * Handles Latin-1, Latin Extended-A/B, Vietnamese, and Chinese Pinyin diacritics.
	 * @param string $string The string from which to remove accents
	 * @return string The string with all accented characters replaced by their ASCII equivalents
	 * @link https://github.com/WordPress/WordPress/blob/a2693fd8602e3263b5925b9d799ddd577202167d/wp-includes/formatting.php#L1528
	 */
	public static function removeAccents(string $string): string
	{
		if (!preg_match('/[\x80-\xff]/', $string)) {
			return $string;
		}

		// $string = strtr($string, "ÀÁÂÃÄÅàáâãäåÒÓÔÕÖØòóôõöøÈÉÊËèéêëÇçÌÍÎÏìíîïÙÚÛÜùúûüÿÑñ", "aaaaaaaaaaaaooooooooooooeeeeeeeecciiiiiiiiuuuuuuuuynn");

		return strtr($string, [
			// Decompositions for Latin-1 Supplement
			'ª' => 'a', 'º' => 'o',
			'À' => 'A', 'Á' => 'A',
			'Â' => 'A', 'Ã' => 'A',
			'Ä' => 'A', 'Å' => 'A',
			'Æ' => 'AE','Ç' => 'C',
			'È' => 'E', 'É' => 'E',
			'Ê' => 'E', 'Ë' => 'E',
			'Ì' => 'I', 'Í' => 'I',
			'Î' => 'I', 'Ï' => 'I',
			'Ð' => 'D', 'Ñ' => 'N',
			'Ò' => 'O', 'Ó' => 'O',
			'Ô' => 'O', 'Õ' => 'O',
			'Ö' => 'O', 'Ù' => 'U',
			'Ú' => 'U', 'Û' => 'U',
			'Ü' => 'U', 'Ý' => 'Y',
			'Þ' => 'TH','ß' => 's',
			'à' => 'a', 'á' => 'a',
			'â' => 'a', 'ã' => 'a',
			'ä' => 'a', 'å' => 'a',
			'æ' => 'ae','ç' => 'c',
			'è' => 'e', 'é' => 'e',
			'ê' => 'e', 'ë' => 'e',
			'ì' => 'i', 'í' => 'i',
			'î' => 'i', 'ï' => 'i',
			'ð' => 'd', 'ñ' => 'n',
			'ò' => 'o', 'ó' => 'o',
			'ô' => 'o', 'õ' => 'o',
			'ö' => 'o', 'ø' => 'o',
			'ù' => 'u', 'ú' => 'u',
			'û' => 'u', 'ü' => 'u',
			'ý' => 'y', 'þ' => 'th',
			'ÿ' => 'y', 'Ø' => 'O',
			// Decompositions for Latin Extended-A
			'Ā' => 'A', 'ā' => 'a',
			'Ă' => 'A', 'ă' => 'a',
			'Ą' => 'A', 'ą' => 'a',
			'Ć' => 'C', 'ć' => 'c',
			'Ĉ' => 'C', 'ĉ' => 'c',
			'Ċ' => 'C', 'ċ' => 'c',
			'Č' => 'C', 'č' => 'c',
			'Ď' => 'D', 'ď' => 'd',
			'Đ' => 'D', 'đ' => 'd',
			'Ē' => 'E', 'ē' => 'e',
			'Ĕ' => 'E', 'ĕ' => 'e',
			'Ė' => 'E', 'ė' => 'e',
			'Ę' => 'E', 'ę' => 'e',
			'Ě' => 'E', 'ě' => 'e',
			'Ĝ' => 'G', 'ĝ' => 'g',
			'Ğ' => 'G', 'ğ' => 'g',
			'Ġ' => 'G', 'ġ' => 'g',
			'Ģ' => 'G', 'ģ' => 'g',
			'Ĥ' => 'H', 'ĥ' => 'h',
			'Ħ' => 'H', 'ħ' => 'h',
			'Ĩ' => 'I', 'ĩ' => 'i',
			'Ī' => 'I', 'ī' => 'i',
			'Ĭ' => 'I', 'ĭ' => 'i',
			'Į' => 'I', 'į' => 'i',
			'İ' => 'I', 'ı' => 'i',
			'Ĳ' => 'IJ','ĳ' => 'ij',
			'Ĵ' => 'J', 'ĵ' => 'j',
			'Ķ' => 'K', 'ķ' => 'k',
			'ĸ' => 'k', 'Ĺ' => 'L',
			'ĺ' => 'l', 'Ļ' => 'L',
			'ļ' => 'l', 'Ľ' => 'L',
			'ľ' => 'l', 'Ŀ' => 'L',
			'ŀ' => 'l', 'Ł' => 'L',
			'ł' => 'l', 'Ń' => 'N',
			'ń' => 'n', 'Ņ' => 'N',
			'ņ' => 'n', 'Ň' => 'N',
			'ň' => 'n', 'ŉ' => 'n',
			'Ŋ' => 'N', 'ŋ' => 'n',
			'Ō' => 'O', 'ō' => 'o',
			'Ŏ' => 'O', 'ŏ' => 'o',
			'Ő' => 'O', 'ő' => 'o',
			'Œ' => 'OE','œ' => 'oe',
			'Ŕ' => 'R','ŕ' => 'r',
			'Ŗ' => 'R','ŗ' => 'r',
			'Ř' => 'R','ř' => 'r',
			'Ś' => 'S','ś' => 's',
			'Ŝ' => 'S','ŝ' => 's',
			'Ş' => 'S','ş' => 's',
			'Š' => 'S', 'š' => 's',
			'Ţ' => 'T', 'ţ' => 't',
			'Ť' => 'T', 'ť' => 't',
			'Ŧ' => 'T', 'ŧ' => 't',
			'Ũ' => 'U', 'ũ' => 'u',
			'Ū' => 'U', 'ū' => 'u',
			'Ŭ' => 'U', 'ŭ' => 'u',
			'Ů' => 'U', 'ů' => 'u',
			'Ű' => 'U', 'ű' => 'u',
			'Ų' => 'U', 'ų' => 'u',
			'Ŵ' => 'W', 'ŵ' => 'w',
			'Ŷ' => 'Y', 'ŷ' => 'y',
			'Ÿ' => 'Y', 'Ź' => 'Z',
			'ź' => 'z', 'Ż' => 'Z',
			'ż' => 'z', 'Ž' => 'Z',
			'ž' => 'z', 'ſ' => 's',
			// Decompositions for Latin Extended-B
			'Ș' => 'S', 'ș' => 's',
			'Ț' => 'T', 'ț' => 't',
			// Euro Sign
			'€' => 'E',
			// GBP (Pound) Sign
			'£' => '',
			// Vowels with diacritic (Vietnamese)
			// unmarked
			'Ơ' => 'O', 'ơ' => 'o',
			'Ư' => 'U', 'ư' => 'u',
			// grave accent
			'Ầ' => 'A', 'ầ' => 'a',
			'Ằ' => 'A', 'ằ' => 'a',
			'Ề' => 'E', 'ề' => 'e',
			'Ồ' => 'O', 'ồ' => 'o',
			'Ờ' => 'O', 'ờ' => 'o',
			'Ừ' => 'U', 'ừ' => 'u',
			'Ỳ' => 'Y', 'ỳ' => 'y',
			// hook
			'Ả' => 'A', 'ả' => 'a',
			'Ẩ' => 'A', 'ẩ' => 'a',
			'Ẳ' => 'A', 'ẳ' => 'a',
			'Ẻ' => 'E', 'ẻ' => 'e',
			'Ể' => 'E', 'ể' => 'e',
			'Ỉ' => 'I', 'ỉ' => 'i',
			'Ỏ' => 'O', 'ỏ' => 'o',
			'Ổ' => 'O', 'ổ' => 'o',
			'Ở' => 'O', 'ở' => 'o',
			'Ủ' => 'U', 'ủ' => 'u',
			'Ử' => 'U', 'ử' => 'u',
			'Ỷ' => 'Y', 'ỷ' => 'y',
			// tilde
			'Ẫ' => 'A', 'ẫ' => 'a',
			'Ẵ' => 'A', 'ẵ' => 'a',
			'Ẽ' => 'E', 'ẽ' => 'e',
			'Ễ' => 'E', 'ễ' => 'e',
			'Ỗ' => 'O', 'ỗ' => 'o',
			'Ỡ' => 'O', 'ỡ' => 'o',
			'Ữ' => 'U', 'ữ' => 'u',
			'Ỹ' => 'Y', 'ỹ' => 'y',
			// acute accent
			'Ấ' => 'A', 'ấ' => 'a',
			'Ắ' => 'A', 'ắ' => 'a',
			'Ế' => 'E', 'ế' => 'e',
			'Ố' => 'O', 'ố' => 'o',
			'Ớ' => 'O', 'ớ' => 'o',
			'Ứ' => 'U', 'ứ' => 'u',
			// dot below
			'Ạ' => 'A', 'ạ' => 'a',
			'Ậ' => 'A', 'ậ' => 'a',
			'Ặ' => 'A', 'ặ' => 'a',
			'Ẹ' => 'E', 'ẹ' => 'e',
			'Ệ' => 'E', 'ệ' => 'e',
			'Ị' => 'I', 'ị' => 'i',
			'Ọ' => 'O', 'ọ' => 'o',
			'Ộ' => 'O', 'ộ' => 'o',
			'Ợ' => 'O', 'ợ' => 'o',
			'Ụ' => 'U', 'ụ' => 'u',
			'Ự' => 'U', 'ự' => 'u',
			'Ỵ' => 'Y', 'ỵ' => 'y',
			// Vowels with diacritic (Chinese, Hanyu Pinyin)
			'ɑ' => 'a',
			// macron
			'Ǖ' => 'U', 'ǖ' => 'u',
			// acute accent
			'Ǘ' => 'U', 'ǘ' => 'u',
			// caron
			'Ǎ' => 'A', 'ǎ' => 'a',
			'Ǐ' => 'I', 'ǐ' => 'i',
			'Ǒ' => 'O', 'ǒ' => 'o',
			'Ǔ' => 'U', 'ǔ' => 'u',
			'Ǚ' => 'U', 'ǚ' => 'u',
			// grave accent
			'Ǜ' => 'U', 'ǜ' => 'u',
		]);
	}

	/**
	 * Converts a string to a URL-friendly format (slug).
	 * Removes accents, converts to lowercase, replaces spaces with dashes, and removes special characters.
	 * @param string $str The string to convert
	 * @return string The URL-friendly string (e.g., "Hello World!" becomes "hello-world")
	 */
	public static function toURLFriendly(string $str): string
	{
		$str = self::removeAccents($str);
		$str = preg_replace(['/[^a-zA-Z0-9 \'-]/', '/[ -\']+/', '/^-|-$/'], ['', '-', ''], $str);
		$str = preg_replace('/-inc$/i', '', $str);
		return mb_strtolower($str);
	}

	/**
	 * Unserializes a string that contains multi-byte UTF-8 characters.
	 * Fixes string length issues in serialized data caused by multi-byte characters.
	 * @param string $string The serialized string to unserialize
	 * @return array|null The unserialized array, or null if unserialization fails
	 */
	public static function mb_unserialize(string $string): ?array
	{
		$array = unserialize(preg_replace_callback('!s:(\d+):"(.*?)";!s', static fn($m) => 's:' .strlen($m[2]).':"'.$m[2].'";', $string));
		return $array !== false ? $array : null;
	}

	/**
	 * Multi-byte safe version of ucfirst().
	 * Capitalizes the first character of a string while preserving multi-byte characters.
	 * @param string $string The string to capitalize
	 * @return string The string with the first character capitalized
	 */
	public static function mb_ucfirst(string $string): string
	{
		return mb_strtoupper(mb_substr($string, 0, 1)).mb_substr($string, 1);
	}

	// ========== Random ==========

	/**
	 * Alias of the StringGenerator::getRandomPronounceableWord() function
	 */
	public static function getRandomPronounceableWord(int $length, ?string $consonantsList=null, ?string $vowelsList=null, bool $randomFirstLetter=false, bool $startWithConsonant=true): string
	{
		return StringGenerator::getRandomPronounceableWord($length, $consonantsList, $vowelsList, $randomFirstLetter, $startWithConsonant);
	}

	/**
	 * Alias of the StringGenerator::getRandomString() function
	 */
	public static function getRandomString(int $length, string $charactersList): string
	{
		return StringGenerator::getRandomString($length, $charactersList);
	}

	/**
	 * Alias of the StringGenerator::getRandomAlphaString() function
	 */
	public static function getRandomAlphaString(int $length, bool $uppercaseEnabled=false, bool $lowercaseEnabled=true): string
	{
		return StringGenerator::getRandomAlphaString($length, $uppercaseEnabled, $lowercaseEnabled);
	}

	/**
	 * Alias of the StringGenerator::getRandomNumericString() function
	 */
	public static function getRandomNumericString(int $length, bool $startWith0=false): string
	{
		return StringGenerator::getRandomNumericString($length, $startWith0);
	}

	/**
	 * Alias of the StringGenerator::getRandomAlphanumericString() function
	 */
	public static function getRandomAlphanumericString(int $length, bool $uppercaseEnabled=false, bool $lowercaseEnabled=true): string
	{
		return StringGenerator::getRandomAlphanumericString($length, $uppercaseEnabled, $lowercaseEnabled);
	}

	// ========== DEPRECATED METHODS (Backward Compatibility) ==========

	/**
	 * @deprecated Use isLowercase() instead
	 */
	public static function checkLowercase(string $string, bool $numericAllowed=true): bool
	{
		return self::isLowercase($string, $numericAllowed);
	}

	/**
	 * @deprecated Use isUppercase() instead
	 */
	public static function checkUppercase(string $string, bool $numericAllowed=true): bool
	{
		return self::isUppercase($string, $numericAllowed);
	}

	/**
	 * @deprecated Use hasLengthBetween() instead
	 */
	public static function checkLength(string $string, int $nbCharMin, int $nbCharMax): bool
	{
		return self::hasLengthBetween($string, $nbCharMin, $nbCharMax);
	}

	/**
	 * @deprecated Use isAlphabeticWithLength() instead
	 */
	public static function checkForAlphabeticCharacters(string $string, int $nbCharMin, int $nbCharMax): bool
	{
		return self::isAlphabeticWithLength($string, $nbCharMin, $nbCharMax);
	}

	/**
	 * @deprecated Use isAlphanumericWithLength() instead
	 */
	public static function checkForAlphanumericCharacters(string $string, int $nbCharMin, int $nbCharMax): bool
	{
		return self::isAlphanumericWithLength($string, $nbCharMin, $nbCharMax);
	}

	/**
	 * @deprecated Use isNumericWithLength() instead
	 */
	public static function checkForNumericCharacters(string $string, int $nbCharMin, int $nbCharMax, bool $canStartWithZero=true): bool
	{
		return self::isNumericWithLength($string, $nbCharMin, $nbCharMax, $canStartWithZero);
	}
}