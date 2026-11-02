<?php

namespace Osimatic\Person;

/**
 * Class NameFormatter
 * Provides utilities for formatting person names with proper capitalization and display formats.
 * Handles first names, last names, and full name formatting according to standard conventions.
 */
class NameFormatter
{
	public function __construct()
	{

	}

	/**
	 * Formats a title code into its string representation.
	 * This method is currently a placeholder for future title formatting functionality.
	 * When implemented, it will translate numeric title codes into their string equivalents.
	 * @param int|null $title The title code to format
	 * @return string The formatted title, currently always returns empty string
	 */
	public function formatTitle(?int $title): string
	{
		//if ($title == 1) return $this->translator->trans('title_male', [], null, $locale);
		//if ($title == 2) return $this->translator->trans('title_female', [], null, $locale);
		return '';
	}

	/**
	 * Formats a first name with optional proper capitalization.
	 * Trims whitespace and applies proper capitalization rules if requested.
	 * Handles special cases like hyphenated names and names with apostrophes.
	 * @param string|null $firstName The first name to format
	 * @param bool $ucName Whether to apply proper capitalization (default: true)
	 * @return string The formatted first name, or empty string if input is null
	 */
	public function formatFirstName(?string $firstName, bool $ucName=true): string
	{
		if ($firstName !== null) {
			$firstName = trim($firstName);
			if ($ucName) {
				$firstName = self::ucname($firstName);
			}
			return $firstName;
		}
		return '';
	}

	/**
	 * Formats a last name with optional uppercase conversion.
	 * Trims whitespace and converts to uppercase if requested.
	 * By default, last names are converted to uppercase following common formatting conventions.
	 * @param string|null $lastName The last name to format
	 * @param bool $upperCase Whether to convert to uppercase (default: true)
	 * @return string The formatted last name, or empty string if input is null
	 */
	public function formatLastName(?string $lastName, bool $upperCase=true): string
	{
		if ($lastName !== null) {
			$lastName = trim($lastName);
			if ($upperCase) {
				$lastName = mb_strtoupper($lastName);
			}
			return $lastName;
		}
		return '';
	}

	/**
	 * Formats a complete Name object into a display string.
	 * Combines the formatted first name and last name with proper spacing.
	 * Title formatting is currently disabled but can be enabled in the future.
	 * @param Name $name The Name object to format
	 * @param bool $editCase Whether to apply case formatting (default: true)
	 * @return string The formatted full name
	 */
	public function format(Name $name, bool $editCase=true): string
	{
		//$nameDisplay = $this->formatTitle($name->getTitle(), $locale);
		$nameDisplay = '';
		$nameDisplay = trim($nameDisplay.' '.$this->formatFirstName($name->getFirstName(), $editCase));
		$nameDisplay = trim($nameDisplay.' '.$this->formatLastName($name->getLastName(), $editCase));
		return $nameDisplay;
	}

	/**
	 * Converts a string to proper name capitalization with multi-byte support.
	 * Applies title case conversion while properly handling special delimiters
	 * commonly found in names (hyphens and apostrophes). This ensures names like
	 * "Jean-Pierre" or "O'Brien" are correctly capitalized.
	 * @param string|null $string The string to capitalize
	 * @return string The properly capitalized string
	 */
	public static function ucname(?string $string): string
	{
		$string = mb_convert_case($string ?? '', MB_CASE_TITLE, 'UTF-8');
		foreach (['-', '\''] as $delimiter) {
			if (str_contains($string, $delimiter)) {
				$string = implode($delimiter, array_map(fn($value) => mb_convert_case($value, MB_CASE_TITLE, 'UTF-8'), explode($delimiter, $string)));
			}
		}
		return $string;
	}

}