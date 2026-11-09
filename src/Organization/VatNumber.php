<?php

namespace Osimatic\Organization;

/**
 * Class VatNumber
 * Provides utilities for VAT number validation and formatting
 */
class VatNumber
{
	// ========== Properties ==========

	/**
	 * Optional SOAP client used for VIES requests, mainly to inject a fake client in tests.
	 * When null, a new SoapClient is created for each request.
	 * @link https://www.php.net/manual/en/class.soapclient.php
	 */
	private static ?\SoapClient $viesClient = null;

	// ========== Configuration ==========

	/**
	 * Sets the SOAP client used to query the VIES service.
	 * Pass null to restore the default behavior (a new SoapClient per request).
	 * @param \SoapClient|null $client The SOAP client to use for VIES requests
	 * @return void
	 * @link https://ec.europa.eu/taxation_customs/vies/ VIES service
	 */
	public static function setViesClient(?\SoapClient $client): void
	{
		self::$viesClient = $client;
	}

	// ========== Formatting ==========

	/**
	 * Formats a VAT number for display
	 * @param string $vatNumber the VAT number to format
	 * @return string the formatted VAT number
	 * @todo implement VAT number formatting logic
	 */
	public static function format(string $vatNumber): string
	{
		// todo
		return $vatNumber;
	}

	/**
	 * Validates a VAT number syntax and optionally checks validity via VIES
	 * @param string $vatNumber the VAT number to check (including country code)
	 * @param bool $checkValidity whether to verify with VIES service (default: true)
	 * @return bool true if valid, false otherwise
	 */
	public static function isValid(string $vatNumber, bool $checkValidity=true): bool
	{
		if (empty($vatNumber)) {
			return false;
		}

		$originalVatNumber = $vatNumber;
		$countryCode = substr($vatNumber, 0, 2);
		$vatNumber = substr($vatNumber, 2);

		// Syntax validation
		if (!preg_match('/^[A-Z]{2}$/', $countryCode) || !preg_match('/^[0-9A-Za-z\+\*\.]{2,12}$/', $vatNumber)) {
			return false;
		}

		// --- France/Monaco specific validation ---
		if ('FR' === $countryCode) {
			// France
			if (strlen($vatNumber) === 11) {
				// SIREN validation
				$siren = substr($vatNumber, 2);
				if (!Company::isValidCompanyNumber('FR', $siren)) {
					return false;
				}
			}
			// Monaco
			elseif (strlen($vatNumber) === 9) {
				// SSEE number validation
				$siren = substr($vatNumber, 2);
			}
			else {
				return false;
			}

			// VAT key validation
			$vatKey = (int) substr($vatNumber, 0, 2);
			$theoricalVatKey = ( ( ($siren % 97) * 3 ) + 12 ) % 97;
			return ($vatKey === $theoricalVatKey);
		}

		// Validity check via VIES service
		if ($checkValidity) {
			return self::verifyWithVies($originalVatNumber);
		}

		return true;
	}

	/**
	 * Verifies a VAT number using the EU VIES (VAT Information Exchange System) service
	 * Makes an external call to the VIES SOAP service to check if the VAT number is registered
	 * @param string $vatNumber the VAT number to verify (including country code)
	 * @return bool true if registered in VIES, false otherwise
	 */
	public static function verifyWithVies(string $vatNumber): bool
	{
		$countryCode = substr($vatNumber, 0, 2);
		$vatNumber = substr($vatNumber, 2);

		try {
			$client = self::$viesClient ?? new \SoapClient('http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl');
			$response = $client->checkVat(['countryCode' => $countryCode, 'vatNumber' => $vatNumber]);
			if (!$response->valid) {
				return false;
			}
		}
		catch (\SoapFault) {
			return false;
		}
		return true;

		/*
		// REST API method: disabled due to concurrent request issues and false negatives
		$url = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';
		$body = [
			'countryCode' => $countryCode,
			'vatNumber' => $vatNumber,
		];

		$httpClient = new HTTPClient();
		$json = $httpClient->jsonRequest(HTTPMethod::POST, $url, $body, [], true);
		if (null === $json) {
			return false;
		}

		return $json['valid'] ?? false;
		*/
	}


	// ========================================
	// DEPRECATED METHODS (Backward Compatibility)
	// ========================================

	/**
	 * @deprecated Use isValid() instead
	 * @param string $vatNumber the VAT number to check (including country code)
	 * @param bool $checkValidity whether to verify with VIES service (default: true)
	 * @return bool true if valid, false otherwise
	 */
	public static function check(string $vatNumber, bool $checkValidity=true): bool
	{
		return self::isValid($vatNumber, $checkValidity);
	}

	/**
	 * @deprecated Use verifyWithVies() instead
	 * @param string $vatNumber the VAT number to validate (including country code)
	 * @return bool true if valid according to VIES, false otherwise
	 */
	public static function checkValidity(string $vatNumber): bool
	{
		return self::verifyWithVies($vatNumber);
	}
}