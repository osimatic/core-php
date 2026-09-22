<?php

namespace Osimatic\Location;

use CommerceGuys\Addressing\Address;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use Symfony\Component\Yaml\Yaml;

/**
 * Utility class for postal address validation, formatting, and display.
 * Provides methods for validating address components, formatting addresses according to country conventions,
 * and normalizing special characters.
 */
class PostalAddress
{
	/**
	 * Static cache for postal code regex patterns to avoid re-parsing YAML file on each validation.
	 * @var array|null
	 */
	private static ?array $postalCodeRegex = null;

	// ========== Validation ==========

	/**
	 * Validate a street address.
	 * @param string|null $value The street address to validate
	 * @return bool True if valid
	 */
	public static function isValidStreet(?string $value): bool
	{
		//return preg_match('/(([0-9]+ )?[a-zA-Z ]){1,200}$/', $value);
		return preg_match('/^(.){1,200}$/u', $value);
	}

	/**
	 * Validate a postal code, optionally using country-specific format.
	 * @param string|null $value The postal code to validate
	 * @param string|null $country The ISO 3166-1 alpha-2 country code for country-specific validation
	 * @return bool True if valid
	 */
	public static function isValidPostalCode(?string $value, ?string $country=null): bool
	{
		// If country is provided, validate using country-specific postal code format
		if (null !== $country) {
			$regex = self::getPostalCodeRegex($country);
			if (null !== $regex) {
				return preg_match('/^'.$regex.'$/u', $value);
			}
		}

		return preg_match('/^([\-\.\s\w]){3,15}$/u', $value);
	}

	/**
	 * Alias for isValidPostalCode().
	 * @param string|null $value The ZIP code to validate
	 * @return bool True if valid
	 */
	public static function isValidZipCode(?string $value): bool
	{
		return self::isValidPostalCode($value);
	}

	/**
	 * Validate a city name.
	 * @param string|null $value The city name to validate
	 * @return bool True if valid
	 */
	public static function isValidCity(?string $value): bool
	{
		// /^([a-zA-Z'àâäéèêëìîïòôöùûüçÀÂÄÉÈÊËÌÎÏÒÔÖÙÛÜÇ\s-]){2-100}$/
		//return preg_match('/^[a-zA-Z\'àâäéèêëìîïòôöùûüçÀÂÄÉÈÊËÌÎÏÒÔÖÙÛÜÇ\s-]+$/u', $value);
		return preg_match('/^(.){1,100}$/u', $value);
	}


	// ========== Display ==========

	/**
	 * Format a postal address according to the country's conventions using commerceguys/addressing library.
	 * Automatically formats address components in the correct order based on the country code.
	 * @param PostalAddressInterface $postalAddress The postal address to format
	 * @param bool $withAttention Include the attention line (recipient name) in the output
	 * @param string|null $separator Separator between address lines (default: '<br/>')
	 * @param string|null $locale Locale for formatting (default: system locale)
	 * @return string|null The formatted address string, or null if country code is missing
	 * @link https://github.com/commerceguys/addressing
	 */
	public static function format(PostalAddressInterface $postalAddress, bool $withAttention=true, ?string $separator='<br/>', ?string $locale=null): ?string
	{
		//return (new PostalAddressFormatter())->format($postalAddress, [], $separator, $withAttention);

		$addressFormatRepository = new AddressFormatRepository();
		$countryRepository = new CountryRepository();
		$subdivisionRepository = new SubdivisionRepository();
		$formatter = new DefaultFormatter($addressFormatRepository, $countryRepository, $subdivisionRepository, ['locale' => $locale ?? \Locale::getDefault()]);
		// Options passed to the constructor or format() allow turning off
		// html rendering, customizing the wrapper element and its attributes.

		if (null === $postalAddress->getCountryCode()) {
			return null;
		}

		$address = new Address();
		$address = $address->withCountryCode($postalAddress->getCountryCode());
		if (null !== $postalAddress->getCity()) {
			$address = $address->withLocality($postalAddress->getCity());
		}
		if (null !== $postalAddress->getPostcode()) {
			$address = $address->withPostalCode($postalAddress->getPostcode());
		}
		if (null !== $postalAddress->getRoad()) {
			$address = $address->withAddressLine1($postalAddress->getRoad());
		}
		if (null !== $postalAddress->getState()) {
			$address = $address->withAdministrativeArea($postalAddress->getState());
		}
		if ($withAttention) {
			if (null !== $postalAddress->getAttention()) {
				$address = $address->withFamilyName($postalAddress->getAttention());
			}
		}

		try {
			$formattedAddress = $formatter->format($address, ['html' => false]);
			$formattedAddress = str_replace("\n", $separator, $formattedAddress);
			return $formattedAddress;
		}
		catch (\ReflectionException) {}

		return null;
	}

	/**
	 * Format a postal address as an inline string (single line with comma separators).
	 * @param PostalAddressInterface $postalAddress The postal address to format
	 * @param bool $withAttention Include the attention line (recipient name) in the output
	 * @param string|null $separator Separator between address components (default: ', ')
	 * @return string|null The formatted inline address string, or null if country code is missing
	 */
	public static function formatInline(PostalAddressInterface $postalAddress, bool $withAttention=true, ?string $separator=', ', ?string $locale=null): ?string
	{
		return self::format($postalAddress, $withAttention, $separator, $locale);
	}

	/**
	 * Format a postal address from individual components according to the country's conventions.
	 * This is a convenience method that creates an address from components and formats it.
	 * @param string $countryCode The ISO 3166-1 alpha-2 country code (e.g., 'FR', 'US')
	 * @param string|null $city The city/locality name
	 * @param string|null $postcode The postal code
	 * @param string|null $road The street address (address line 1)
	 * @param string|null $state The state/administrative area (province, region, etc.)
	 * @param string|null $attention The recipient name or attention line
	 * @param string|null $separator Separator between address lines (default: '<br/>')
	 * @param string|null $locale Locale for formatting (default: system locale)
	 * @return string|null The formatted address string, or null on error
	 * @link https://github.com/commerceguys/addressing
	 */
	public static function formatFromComponents(
		string $countryCode,
		?string $city = null,
		?string $postcode = null,
		?string $road = null,
		?string $state = null,
		?string $attention = null,
		?string $separator = '<br/>',
		?string $locale = null
	): ?string
	{
		$addressFormatRepository = new AddressFormatRepository();
		$countryRepository = new CountryRepository();
		$subdivisionRepository = new SubdivisionRepository();
		$formatter = new DefaultFormatter($addressFormatRepository, $countryRepository, $subdivisionRepository, ['locale' => $locale ?? \Locale::getDefault()]);

		$address = new Address();
		$address = $address->withCountryCode($countryCode);

		if (null !== $city) {
			$address = $address->withLocality($city);
		}
		if (null !== $postcode) {
			$address = $address->withPostalCode($postcode);
		}
		if (null !== $road) {
			$address = $address->withAddressLine1($road);
		}
		if (null !== $state) {
			$address = $address->withAdministrativeArea($state);
		}
		if (null !== $attention) {
			$address = $address->withFamilyName($attention);
		}

		try {
			$formattedAddress = $formatter->format($address, ['html' => false]);
			$formattedAddress = str_replace("\n", $separator, $formattedAddress);
			return $formattedAddress;
		}
		catch (\ReflectionException) {}

		return null;
	}

	/**
	 * Format a postal address from individual components as an inline string (single line with comma separators).
	 * @param string $countryCode The ISO 3166-1 alpha-2 country code (e.g., 'FR', 'US')
	 * @param string|null $city The city/locality name
	 * @param string|null $postcode The postal code
	 * @param string|null $road The street address (address line 1)
	 * @param string|null $state The state/administrative area (province, region, etc.)
	 * @param string|null $attention The recipient name or attention line
	 * @param string|null $separator Separator between address components (default: ', ')
	 * @param string|null $locale Locale for formatting (default: system locale)
	 * @return string|null The formatted inline address string, or null on error
	 */
	public static function formatInlineFromComponents(
		string $countryCode,
		?string $city = null,
		?string $postcode = null,
		?string $road = null,
		?string $state = null,
		?string $attention = null,
		?string $separator = ', ',
		?string $locale = null
	): ?string
	{
		return self::formatFromComponents($countryCode, $city, $postcode, $road, $state, $attention, $separator, $locale);
	}




	// ========== Private Methods ==========

	/**
	 * Get the postal code regex pattern for a specific country.
	 * Loads and caches the patterns from postal_codes.yaml on first call.
	 * @param string $country The ISO 3166-1 alpha-2 country code
	 * @return string|null The regex pattern for the country, or null if not found
	 */
	private static function getPostalCodeRegex(string $country): ?string
	{
		// Load regex patterns only once and cache them
		if (null === self::$postalCodeRegex) {
			self::$postalCodeRegex = Yaml::parse(
				file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'conf'.DIRECTORY_SEPARATOR.'postal_codes.yaml')
			);
		}

		return self::$postalCodeRegex[$country] ?? null;
	}


	// ========== Formatting ==========

	/**
	 * Replace special characters in address strings with standard equivalents.
	 * Normalizes various Unicode characters that may appear in addresses from different sources (e.g., Google Maps).
	 * @param string|null $value The address string to normalize
	 * @return string|null The normalized address string
	 */
	public static function replaceSpecialChar(?string $value): ?string
	{
		if (null === $value) {
			return null;
		}

		// Arabic comma sometimes used to separate street from city (e.g., Tunisian addresses from Google Maps, coordinates 36.7691557,10.2432981)
		$value = str_replace('،', ',', $value);

		// Combining acute accent sometimes used for apostrophe
		$value = str_replace('́', '’', $value);

		// Numero sign sometimes used for street number (e.g., Réunion addresses from Google Maps, coordinates -21.0506425,55.2241411)
		$value = str_replace('№', 'N°', $value);

		// Country name sometimes returned in Cyrillic by Google Maps geocoding (e.g., "Франция" for France)
		$value = str_replace('Франция', 'France', $value);

		return \Osimatic\Text\Str::fixInvalidUtf8(\Osimatic\Text\Str::replaceAnnoyingChar($value));
	}



	// ========== DEPRECATED METHODS (Backward Compatibility) ==========

	/**
	 * @deprecated Use isValidStreet() instead
	 * @param string|null $value The street address to validate
	 * @return bool True if valid
	 */
	public static function checkStreet(?string $value): bool
	{
		return self::isValidStreet($value);
	}

	/**
	 * @deprecated Use isValidPostalCode() instead
	 * @param string|null $value The postal code to validate
	 * @param string|null $country The ISO 3166-1 alpha-2 country code for country-specific validation
	 * @return bool True if valid
	 */
	public static function checkPostalCode(?string $value, ?string $country=null): bool
	{
		return self::isValidPostalCode($value, $country);
	}

	/**
	 * @deprecated Use isValidZipCode() instead
	 * @param string|null $value The ZIP code to validate
	 * @return bool True if valid
	 */
	public static function checkZipCode(?string $value): bool
	{
		return self::isValidZipCode($value);
	}

	/**
	 * @deprecated Use isValidCity() instead
	 * @param string|null $value The city name to validate
	 * @return bool True if valid
	 */
	public static function checkCity(?string $value): bool
	{
		return self::isValidCity($value);
	}

	/**
	 * @deprecated use formatInline instead
	 * @param PostalAddressInterface $postalAddress
	 * @param string|null $separator
	 * @return string|null
	 */
	public static function formatInlineFromTwig(PostalAddressInterface $postalAddress, ?string $separator=', '): ?string
	{
		return self::format($postalAddress, $separator);
	}

	/**
	 * @deprecated use format instead
	 * @param PostalAddressInterface $postalAddress
	 * @param bool $withAttention
	 * @param string|null $separator
	 * @return string|null
	 */
	public static function formatFromTwig(PostalAddressInterface $postalAddress, bool $withAttention=true, ?string $separator='<br/>'): ?string
	{
		return self::format($postalAddress, $withAttention, $separator);
	}

	/**
	 * @deprecated use format instead
	 * @param PostalAddressInterface $postalAddress
	 * @param bool $withAttention
	 * @param string|null $separator
	 * @return string|null
	 */
	public static function _format(PostalAddressInterface $postalAddress, bool $withAttention=true, ?string $separator='<br/>'): ?string
	{
		return (new PostalAddressFormatter())->format($postalAddress, [], $separator, $withAttention);
	}

	/**
	 * @deprecated use formatInline instead
	 * @param PostalAddressInterface $postalAddress
	 * @param bool $withAttention
	 * @param string|null $separator
	 * @return string|null
	 */
	public static function _formatInline(PostalAddressInterface $postalAddress, bool $withAttention=true, ?string $separator=', '): ?string
	{
		return self::_format($postalAddress, $withAttention, $separator);
	}

}