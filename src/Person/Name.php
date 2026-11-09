<?php

namespace Osimatic\Person;

/**
 * Class Name
 * Represents a person's name with validation, formatting, and name day lookup functionality.
 * Provides methods to validate name formats, format names for display, and retrieve name day celebrations.
 */
class Name
{
	private Gender $gender = Gender::UNKNOWN;
	private ?string $firstName = null;
	private ?string $lastName = null;

	// ========== Validation ==========

	/**
	 * Validates a civility/title code.
	 * Checks if the value is a valid civility code (0, 1, or 2).
	 * @param string|int|null $value The civility code to validate
	 * @return bool True if valid, false otherwise
	 */
	public static function isValidCivility(string|int|null $value): bool
	{
		return preg_match('/[0-2]/', (string) $value);
	}

	/**
	 * Validates a first name format.
	 * Checks if the first name contains only valid characters (letters, accented characters,
	 * hyphens, apostrophes, and optionally numbers) and has a length between 3 and 120 characters.
	 * @param string|null $value The first name to validate
	 * @param bool $numbersAllowed Whether numbers are allowed in the name (default: false)
	 * @return bool True if valid, false otherwise
	 */
	public static function isValidFirstName(?string $value, bool $numbersAllowed=false): bool
	{
		return preg_match('/^(['.($numbersAllowed?'0-9':'').'a-zA-Z\'àâäéèêëìîïòôöùûüçÀÂÄÉÈÊËÌÎÏÒÔÖÙÛÜÇ\s-]{3,120})$/u', $value ?? '');
	}

	/**
	 * Validates a given name format (alias for isValidFirstName).
	 * This method is an alias for isValidFirstName, following Schema.org naming conventions.
	 * @param string|null $value The given name to validate
	 * @param bool $numbersAllowed Whether numbers are allowed in the name (default: false)
	 * @return bool True if valid, false otherwise
	 */
	public static function isValidGivenName(?string $value, bool $numbersAllowed=false): bool
	{
		return self::isValidFirstName($value, $numbersAllowed);
	}

	/**
	 * Validates a last name format.
	 * Checks if the last name contains only valid characters (letters, accented characters,
	 * hyphens, apostrophes, and optionally numbers) and has a length between 2 and 120 characters.
	 * @param string|null $value The last name to validate
	 * @param bool $numbersAllowed Whether numbers are allowed in the name (default: false)
	 * @return bool True if valid, false otherwise
	 */
	public static function isValidLastName(?string $value, bool $numbersAllowed=false): bool
	{
		return preg_match('/^(['.($numbersAllowed?'0-9':'').'a-zA-Z\'àâäéèêëìîïòôöùûüçÀÂÄÉÈÊËÌÎÏÒÔÖÙÛÜÇ\s-]{2,120})$/u', $value ?? '');
	}

	/**
	 * Validates a family name format (alias for isValidLastName).
	 * This method is an alias for isValidLastName, following Schema.org naming conventions.
	 * @param string|null $value The family name to validate
	 * @param bool $numbersAllowed Whether numbers are allowed in the name (default: false)
	 * @return bool True if valid, false otherwise
	 */
	public static function isValidFamilyName(?string $value, bool $numbersAllowed=false): bool
	{
		return self::isValidLastName($value, $numbersAllowed);
	}


	// ========== Display ==========

	/**
	 * Returns a formatted name string from individual components.
	 * Creates a temporary Name object and formats it using NameFormatter.
	 * @param Gender|null $gender The gender of the person
	 * @param string|null $firstName The first name
	 * @param string|null $lastName The last name
	 * @return string|null The formatted name or null
	 */
	public static function getFormattedName(?Gender $gender, ?string $firstName, ?string $lastName): ?string
	{
		return (new NameFormatter())->format(
			(new self())
				->setGender($gender ?? Gender::UNKNOWN)
				->setFirstName($firstName)
				->setLastName($lastName)
		);
	}

	/**
	 * Formats a Name object for use in Twig templates.
	 * This is a convenience method for template rendering.
	 * @param Name $name The Name object to format
	 * @return string|null The formatted name or null
	 */
	public static function formatFromTwig(Name $name): ?string
	{
		return (new NameFormatter())->format($name);
	}

	/**
	 * Formats this Name object into a display string.
	 * @return string|null The formatted name or null
	 */
	public function format(): ?string
	{
		return (new NameFormatter())->format($this);
	}

	/**
	 * Converts the Name object to a string.
	 * Returns the formatted name or an empty string if formatting fails.
	 * @return string The formatted name or empty string
	 */
	public function __toString()
	{
		return $this->format() ?? '';
	}

	// ========== Name Day ==========

	/**
	 * Returns the main name day celebration for a given date.
	 * Returns the primary first name indicated on the official calendar for the specified date.
	 * @param int|null $month The month of the name day (default: current month)
	 * @param int|null $day The day of month of the name day (default: current day)
	 * @param string $country The country code (default: 'FR')
	 * @param bool $rare Whether to include rare name days (default: false)
	 * @return string|null The name day or null if not found
	 */
	public static function getNameDay(?int $month=null, ?int $day=null, string $country='FR', bool $rare=false): ?string
	{
		return self::getNameDays($month, $day, $country, $rare)[0] ?? null;
	}

	/**
	 * Returns the list of all name day celebrations for a given date.
	 * Returns all first names associated with the specified date, including secondary celebrations.
	 * @param int|null $month The month of the name day (default: current month)
	 * @param int|null $day The day of month of the name day (default: current day)
	 * @param string $country The country code (default: 'FR')
	 * @param bool $rare Whether to include rare name days (default: false)
	 * @return array The list of name days for the specified date
	 */
	public static function getNameDays(?int $month=null, ?int $day=null, string $country='FR', bool $rare=false): array
	{
		$month ??= date('m');
		$day ??= date('d');
		return self::getNameDaysList($country, $rare)[sprintf("%02d", $day).'/'.sprintf("%02d", $month)] ?? [];
	}

	private static array $nameDays = [];

	/**
	 * Returns the complete list of name days for a country.
	 * Loads name day data from a CSV file and caches it for subsequent calls.
	 * The CSV file should be located at conf/name_days_{country}.csv.
	 * @param string $country The country code (default: 'FR')
	 * @param bool $rare Whether to include rare name days (default: false)
	 * @param bool $special Whether to include special celebrations like New Year, Christian holidays, etc. (default: false)
	 * @return array Associative array with dates as keys (format: DD/MM) and arrays of names as values
	 */
	public static function getNameDaysList(string $country='FR', bool $rare=false, bool $special=false): array
	{
		if ((self::$nameDays[$country] ?? null) === null) {
			// self::$nameDays[$country] = str_getcsv(file_get_contents(), ';');
			self::$nameDays[$country] = [];

			$filePath = __DIR__.'/conf/name_days_'.mb_strtolower($country).'.csv';

			if (!file_exists($filePath) || false === ($handle = fopen($filePath, 'r'))) {
				return [];
			}

			$mainNameDays = [];
			while (($firstNameData = fgetcsv($handle, 1000, separator: ';', escape: "")) !== false) {
				if (count($firstNameData) < 4) {
					continue;
				}

				if (!$rare && (bool)trim($firstNameData[3])) { // if rare name, ignore it
					continue;
				}

				if (!$special && trim($firstNameData[4] ?? 1) === '0') { // if special celebration (e.g., New Year, Christian holidays...), ignore it
					continue;
				}

				$keyDate = trim($firstNameData[1]);
				$firstName = trim($firstNameData[0]);
				if ((bool)trim($firstNameData[2])) { // if main name
					$mainNameDays[$keyDate] = $firstName;
					continue;
				}
				self::$nameDays[$country][$keyDate][] = $firstName;
			}

			// add main names at the beginning of each daily array
			foreach ($mainNameDays as $key => $firstName) {
				if (!isset(self::$nameDays[$country][$key])) {
					self::$nameDays[$country][$key] = [];
				}
				array_unshift(self::$nameDays[$country][$key], $firstName);
			}

			fclose($handle);
		}

		return self::$nameDays[$country];
	}

	// ========== Getters / Setters ==========

	/**
	 * Gets the gender of the person.
	 * @return Gender The gender enum value
	 */
	public function getGender(): Gender
	{
		return $this->gender;
	}

	/**
	 * Sets the gender of the person.
	 * @param Gender $gender The gender enum value to set
	 * @return self Returns this instance for method chaining
	 */
	public function setGender(Gender $gender): self
	{
		$this->gender = $gender;

		return $this;
	}

	/**
	 * Gets the first name of the person.
	 * @return string|null The first name or null if not set
	 */
	public function getFirstName(): ?string
	{
		return $this->firstName;
	}

	/**
	 * Sets the first name of the person.
	 * @param string|null $firstName The first name to set
	 * @return self Returns this instance for method chaining
	 */
	public function setFirstName(?string $firstName): self
	{
		$this->firstName = $firstName;

		return $this;
	}

	/**
	 * Gets the last name of the person.
	 * @return string|null The last name or null if not set
	 */
	public function getLastName(): ?string
	{
		return $this->lastName;
	}

	/**
	 * Sets the last name of the person.
	 * @param string|null $lastName The last name to set
	 * @return self Returns this instance for method chaining
	 */
	public function setLastName(?string $lastName): self
	{
		$this->lastName = $lastName;

		return $this;
	}

	/**
	 * Gets the given name of the person (alias for getFirstName).
	 * This method follows Schema.org naming conventions.
	 * @return string|null The given name or null if not set
	 */
	public function getGivenName(): ?string
	{
		return $this->firstName;
	}

	/**
	 * Sets the given name of the person (alias for setFirstName).
	 * This method follows Schema.org naming conventions.
	 * @param string|null $firstName The given name to set
	 * @return self Returns this instance for method chaining
	 */
	public function setGivenName(?string $firstName): self
	{
		$this->firstName = $firstName;

		return $this;
	}

	/**
	 * Gets the family name of the person (alias for getLastName).
	 * This method follows Schema.org naming conventions.
	 * @return string|null The family name or null if not set
	 */
	public function getFamilyName(): ?string
	{
		return $this->lastName;
	}

	/**
	 * Sets the family name of the person (alias for setLastName).
	 * This method follows Schema.org naming conventions.
	 * @param string|null $lastName The family name to set
	 * @return self Returns this instance for method chaining
	 */
	public function setFamilyName(?string $lastName): self
	{
		$this->lastName = $lastName;

		return $this;
	}

	// ========== DEPRECATED METHODS (Backward Compatibility) ==========

	/**
	 * Validates a civility/title code.
	 * @deprecated Use isValidCivility() instead
	 * @param string|int|null $value The civility code to validate
	 * @return bool True if valid, false otherwise
	 */
	public static function checkCivility(string|int|null $value): bool
	{
		return self::isValidCivility($value);
	}

	/**
	 * Validates a first name format.
	 * @deprecated Use isValidFirstName() instead
	 * @param string|null $value The first name to validate
	 * @param bool $numbersAllowed Whether numbers are allowed in the name (default: false)
	 * @return bool True if valid, false otherwise
	 */
	public static function checkFirstName(?string $value, bool $numbersAllowed=false): bool
	{
		return self::isValidFirstName($value, $numbersAllowed);
	}

	/**
	 * Validates a given name format.
	 * @deprecated Use isValidGivenName() instead
	 * @param string|null $value The given name to validate
	 * @param bool $numbersAllowed Whether numbers are allowed in the name (default: false)
	 * @return bool True if valid, false otherwise
	 */
	public static function checkGivenName(?string $value, bool $numbersAllowed=false): bool
	{
		return self::isValidGivenName($value, $numbersAllowed);
	}

	/**
	 * Validates a last name format.
	 * @deprecated Use isValidLastName() instead
	 * @param string|null $value The last name to validate
	 * @param bool $numbersAllowed Whether numbers are allowed in the name (default: false)
	 * @return bool True if valid, false otherwise
	 */
	public static function checkLastName(?string $value, bool $numbersAllowed=false): bool
	{
		return self::isValidLastName($value, $numbersAllowed);
	}

	/**
	 * Validates a family name format.
	 * @deprecated Use isValidFamilyName() instead
	 * @param string|null $value The family name to validate
	 * @param bool $numbersAllowed Whether numbers are allowed in the name (default: false)
	 * @return bool True if valid, false otherwise
	 */
	public static function checkFamilyName(?string $value, bool $numbersAllowed=false): bool
	{
		return self::isValidFamilyName($value, $numbersAllowed);
	}
}
