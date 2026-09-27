<?php

namespace Osimatic\Invoice;

/**
 * Interface for billing address information
 * Represents the address details for billing purposes in payment processing
 */
interface BillingAddressInterface
{
	/**
	 * Get the first name of the billing address holder
	 * @return string|null The first name, or null if not set
	 */
	public function getFirstName(): ?string;

	/**
	 * Set the first name of the billing address holder
	 * @param string|null $firstName The first name to set
	 * @return self|void
	 */
	public function setFirstName(?string $firstName);

	/**
	 * Get the last name of the billing address holder
	 * @return string|null The last name, or null if not set
	 */
	public function getLastName(): ?string;

	/**
	 * Set the last name of the billing address holder
	 * @param string|null $lastName The last name to set
	 * @return self|void
	 */
	public function setLastName(?string $lastName);

	/**
	 * Get the company name for the billing address
	 * @return string|null The company name, or null if not set
	 */
	public function getCompanyName(): ?string;

	/**
	 * Set the company name for the billing address
	 * @param string|null $companyName The company name to set
	 * @return self|void
	 */
	public function setCompanyName(?string $companyName);

	/**
	 * Get the primary street address
	 * @return string The street address
	 */
	public function getStreet(): string;

	/**
	 * Set the primary street address
	 * @param string $street The street address to set
	 * @return self|void
	 */
	public function setStreet(string $street);

	/**
	 * Get the secondary street address line (e.g., apartment, suite number)
	 * @return string|null The secondary street address, or null if not set
	 */
	public function getStreet2(): ?string;

	/**
	 * Set the secondary street address line (e.g., apartment, suite number)
	 * @param string|null $street2 The secondary street address to set
	 * @return self|void
	 */
	public function setStreet2(?string $street2);

	/**
	 * Get the postal/ZIP code
	 * @return string|null The postal code, or null if not set
	 */
	public function getZipCode(): ?string;

	/**
	 * Set the postal/ZIP code
	 * @param string|null $zipCode The postal code to set
	 * @return self|void
	 */
	public function setZipCode(?string $zipCode);

	/**
	 * Get the city name
	 * @return string The city name
	 */
	public function getCity(): string;

	/**
	 * Set the city name
	 * @param string $city The city name to set
	 * @return self|void
	 */
	public function setCity(string $city);

	/**
	 * Get the country code (ISO 3166-1 alpha-2 format)
	 * @return string The two-letter country code (e.g., "FR", "US")
	 */
	public function getCountryCode(): string;

	/**
	 * Set the country code (ISO 3166-1 alpha-2 format)
	 * @param string $countryCode The two-letter country code to set (e.g., "FR", "US")
	 * @return self|void
	 */
	public function setCountryCode(string $countryCode);
}