<?php

namespace Osimatic\Invoice;

/**
 * Class for calculating billing tax rates
 * Determines the applicable VAT/tax rate based on customer and seller locations
 */
class BillingTax
{
	/**
	 * Get the billing tax rate based on customer and seller locations
	 * Implements EU VAT rules and French DOM-TOM specific rates
	 * @param string|null $country The customer's country code (ISO 3166-1 alpha-2)
	 * @param string|null $zipCode The customer's postal/ZIP code (used for French DOM-TOM)
	 * @param string|null $vatNumber The customer's EU intra-community VAT number
	 * @param string $billingCountry The seller's country code (default: "FR")
	 * @return float The tax rate as a percentage (e.g., 20.0 for 20%)
	 */
	public static function getBillingTaxRate(?string $country, ?string $zipCode=null, ?string $vatNumber=null, string $billingCountry='FR'): float
	{
		if (empty($country)) {
			$country = $billingCountry;
		}

		if (\Osimatic\Location\Country::isCountryInFranceOverseas($country)) {
			$country = 'FR';
		}

		// Foreign VAT
		if ($country !== $billingCountry) {
			if (false === \Osimatic\Location\Country::isCountryInEuropeanUnion($billingCountry)) {
				// No VAT, because the billing entity is outside the EU
				return 0.0;
			}

			// The billing country is in the EU

			if (false === \Osimatic\Location\Country::isCountryInEuropeanUnion($country)) {
				// No VAT, because the customer is outside the EU
				return 0.0;
			}

			if (!empty($vatNumber)) {
				// No VAT, because the customer is in the EU and has provided an intra-community VAT number
				return 0.0;
			}

			// The customer is in the EU but hasn't provided a VAT number. Apply the VAT rate of the billing country.
			$country = $billingCountry;
		}

		// French VAT
		if ($country === 'FR') {
			// French overseas departments (DOM-TOM)
			if (!empty($zipCode)) {
				// French Guiana / Mayotte
				if (in_array(substr($zipCode, 0, 3), ['973', '976'], true)) {
					// 0% VAT for French Guiana / Mayotte departments
					return 0.0;
				}
				// Guadeloupe / Martinique / La Réunion
				if (in_array(substr($zipCode, 0, 3), ['971', '972', '974'], true)) {
					// 8.5% VAT for Guadeloupe / Martinique / La Réunion departments
					return 8.5;
				}
			}

			return 20.0;
		}

		// TODO: other countries

		return 0;
	}
}