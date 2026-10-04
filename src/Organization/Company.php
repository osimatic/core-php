<?php

namespace Osimatic\Organization;

/**
 * Class Company
 * Provides utilities for company identification number validation (SIREN, SIRET, NAF codes, etc.)
 */
class Company
{
	/**
	 * Validates a company name syntax
	 * @param string $companyName the company name to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function isValidCompanyName(string $companyName): bool
	{
		return preg_match('/^([0-9a-zA-Z\'&àâäéèêëìîïòôöùûüçÀÂÄÉÈÊËÌÎÏÒÔÖÙÛÜÇ\.\(\)\s\/-]{3,100})$/u', $companyName);
	}

	/**
	 * Validates a company number for a given country
	 * @param string $countryCode the ISO 3166-1 alpha-2 country code
	 * @param string $companyNumber the company number to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function isValidCompanyNumber(string $countryCode, string $companyNumber): bool
	{
		// France
		if ('FR' === $countryCode) {
			return self::isValidFranceSiren($companyNumber);
		}

		return true;
	}

	// ========== FRANCE ==========

	/**
	 * Parses a French SIREN number as entered by a user, removing spaces so it can be validated and stored in its canonical digits-only form
	 * @param string $siren the SIREN number, possibly containing spaces
	 * @return string the cleaned SIREN number
	 */
	public static function parseFranceSiren(string $siren): string
	{
		return \Osimatic\Text\Str::removeSpaces($siren);
	}

	/**
	 * Parses a French SIRET number as entered by a user, removing spaces so it can be validated and stored in its canonical digits-only form
	 * @param string $siret the SIRET number, possibly containing spaces
	 * @return string the cleaned SIRET number
	 */
	public static function parseFranceSiret(string $siret): string
	{
		return \Osimatic\Text\Str::removeSpaces($siret);
	}

	/**
	 * Validates a French SIREN number (Système d'Identification du Répertoire des ENtreprises)
	 * The SIREN consists of 9 digits (8 digits plus a check digit) validated using the Luhn algorithm
	 * @link http://fr.wikipedia.org/wiki/SIREN
	 * @param string $siren the SIREN number to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function isValidFranceSiren(string $siren): bool
	{
		// Syntax validation
		if (!preg_match('#^[0-9]{9}$#', $siren)) {
			return false;
		}
		// Validity check using Luhn algorithm (key "1-2")
		return \Osimatic\Number\Number::checkLuhn((int) $siren);
	}

	/**
	 * Validates a French SIRET number (Système d'identification du répertoire des établissements)
	 * This 14-digit identifier consists of:
	 * - the 9-digit SIREN number of the company
	 * - the NIC (Numéro Interne de Classement): 4-digit sequential number + 1 check digit
	 * @link http://fr.wikipedia.org/wiki/SIRET
	 * @param string $siret the SIRET number to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function isValidFranceSiret(string $siret): bool
	{
		// Syntax validation
		if (!preg_match('#^[0-9]{14}$#', $siret)) {
			return false;
		}
		// SIREN validation
		$siren = substr($siret, 0, 9);
		if (!self::isValidFranceSiren($siren)) {
			return false;
		}
		// SIRET validity check using Luhn algorithm (key "1-2")
		return \Osimatic\Number\Number::checkLuhn((int) $siret);
	}

	/**
	 * Formats a French SIREN number for display, grouped by blocks of 3 digits, e.g. "217 402 379"
	 * @param string $siren the SIREN number, possibly containing spaces
	 * @return string the formatted SIREN number, or the cleaned input unchanged if it is not a 9-digit number
	 */
	public static function formatFranceSiren(string $siren): string
	{
		$siren = self::parseFranceSiren($siren);
		if (9 !== \strlen($siren)) {
			return $siren;
		}
		return rtrim(chunk_split($siren, 3, ' '));
	}

	/**
	 * Formats a French SIRET number for display, grouped as the 9-digit SIREN followed by the 5-digit NIC, e.g. "217 402 379 00069"
	 * @param string $siret the SIRET number
	 * @return string the formatted SIRET number, or the cleaned input unchanged if it is not a 14-digit number
	 */
	public static function formatFranceSiret(string $siret): string
	{
		$siret = self::parseFranceSiret($siret);
		if (14 !== \strlen($siret)) {
			return $siret;
		}
		return chunk_split(substr($siret, 0, 9), 3, ' ').substr($siret, 9);
	}

	/**
	 * Returns the list of French NAF codes according to NAF 2008 (732 positions)
	 * @link http://fr.wikipedia.org/wiki/Code_NAF
	 * @return array the list of NAF codes
	 */
	public static function getFranceApeCodeList(): array
	{
		return parse_ini_file(__DIR__.'/conf/france_code_naf.ini', true);
	}

	/**
	 * Validates a French APE code (Activité Principale Exercée)
	 * @link http://fr.wikipedia.org/wiki/Code_APE
	 * @link https://fr.wikipedia.org/wiki/Activit%C3%A9_principale_exerc%C3%A9e
	 * @param string $codeApe the APE code to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function isValidFranceCodeApe(string $codeApe): bool
	{
		return self::isValidFranceCodeNaf($codeApe);
	}

	/**
	 * Validates a French NAF code (Nomenclature d'Activités Française)
	 * @param string $codeNaf the NAF code to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function isValidFranceCodeNaf(string $codeNaf): bool
	{
		// Accepts formats with or without dot: 01.11Z or 0111Z
		if (!preg_match('#^[0-9]{2}\.?[0-9]{2}[A-Za-z]$#', $codeNaf)) {
			return false;
		}

		// Normalize by removing dot if present
		$codeNaf = str_replace('.', '', $codeNaf);

		return array_key_exists($codeNaf, self::getFranceApeCodeList());
	}

	/**
	 * Returns the label for a French APE code
	 * @param string $ape the APE code
	 * @return string the label for the APE code, empty string if not found
	 */
	public static function getFranceApeLabel(string $ape): string
	{
		// Normalize by removing dot if present
		$ape = str_replace('.', '', $ape);
		return self::getFranceApeCodeList()[$ape] ?? '';
	}

	/**
	 * Formats a SIRET number for display as RCS (Registre du Commerce et des Sociétés)
	 * @link https://fr.wikipedia.org/wiki/Registre_du_commerce_et_des_soci%C3%A9t%C3%A9s_(France)
	 * @param string $siret the SIRET number
	 * @return string the formatted RCS number
	 */
	public static function formatFranceRcs(string $siret): string
	{
		$siren = substr($siret, 0, -5);
		return 'B '.rtrim(chunk_split($siren, 3, ' '));
	}

	// ========== MONACO ==========

	/**
	 * Validates a Monaco NIS number (Numéro d'Identification Statistique)
	 * @param string $nis the NIS number to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function isValidMonacoNis(string $nis): bool
	{
		return preg_match('#^[0-9A-Z]{5,10}$#', $nis);
	}


	// ========================================
	// DEPRECATED METHODS (Backward Compatibility)
	// ========================================

	/**
	 * @deprecated Use isValidCompanyName() instead
	 * @param string $companyName the company name to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function checkCompanyName(string $companyName): bool
	{
		return self::isValidCompanyName($companyName);
	}

	/**
	 * @deprecated Use isValidCompanyNumber() instead
	 * @param string $countryCode the ISO 3166-1 alpha-2 country code
	 * @param string $companyNumber the company number to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function checkCompanyNumber(string $countryCode, string $companyNumber): bool
	{
		return self::isValidCompanyNumber($countryCode, $companyNumber);
	}

	/**
	 * @deprecated Use isValidFranceSiren() instead
	 * @param string $siren the SIREN number to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function checkFranceSiren(string $siren): bool
	{
		return self::isValidFranceSiren($siren);
	}

	/**
	 * @deprecated Use isValidFranceSiret() instead
	 * @param string $siret the SIRET number to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function checkFranceSiret(string $siret): bool
	{
		return self::isValidFranceSiret($siret);
	}

	/**
	 * @deprecated Use isValidFranceCodeApe() instead
	 * @param string $codeApe the APE code to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function checkFranceCodeApe(string $codeApe): bool
	{
		return self::isValidFranceCodeApe($codeApe);
	}

	/**
	 * @deprecated Use isValidFranceCodeNaf() instead
	 * @param string $codeNaf the NAF code to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function checkFranceCodeNaf(string $codeNaf): bool
	{
		return self::isValidFranceCodeNaf($codeNaf);
	}

	/**
	 * @deprecated Use isValidMonacoNis() instead
	 * @param string $nis the NIS number to validate
	 * @return bool true if valid, false otherwise
	 */
	public static function checkMonacoNis(string $nis): bool
	{
		return self::isValidMonacoNis($nis);
	}

}