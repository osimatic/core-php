<?php

declare(strict_types=1);

namespace Tests\Bank;

use Osimatic\Invoice\BillingTax;
use PHPUnit\Framework\TestCase;

final class BillingTaxTest extends TestCase
{
	/* ===================== getBillingTaxRate() - France métropolitaine ===================== */

	public function testGetBillingTaxRateForFranceMetropolitan(): void
	{
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR', '75001'));
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR', '69001'));
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR', '13001'));
	}

	public function testGetBillingTaxRateForFranceWithoutZipCode(): void
	{
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR'));
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR', null));
	}

	/* ===================== getBillingTaxRate() - DOM-TOM ===================== */

	public function testGetBillingTaxRateForGuadeloupe(): void
	{
		$this->assertSame(8.5, BillingTax::getBillingTaxRate('FR', '97100'));
		$this->assertSame(8.5, BillingTax::getBillingTaxRate('FR', '97110'));
	}

	public function testGetBillingTaxRateForMartinique(): void
	{
		$this->assertSame(8.5, BillingTax::getBillingTaxRate('FR', '97200'));
		$this->assertSame(8.5, BillingTax::getBillingTaxRate('FR', '97220'));
	}

	public function testGetBillingTaxRateForReunion(): void
	{
		$this->assertSame(8.5, BillingTax::getBillingTaxRate('FR', '97400'));
		$this->assertSame(8.5, BillingTax::getBillingTaxRate('FR', '97430'));
	}

	public function testGetBillingTaxRateForGuyane(): void
	{
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('FR', '97300'));
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('FR', '97310'));
	}

	public function testGetBillingTaxRateForMayotte(): void
	{
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('FR', '97600'));
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('FR', '97610'));
	}

	/* ===================== getBillingTaxRate() - Empty country ===================== */

	public function testGetBillingTaxRateWithEmptyCountry(): void
	{
		// Si le pays est vide, utilise le pays de facturation (FR par défaut)
		$this->assertSame(20.0, BillingTax::getBillingTaxRate(null));
		$this->assertSame(20.0, BillingTax::getBillingTaxRate(''));
	}

	public function testGetBillingTaxRateWithEmptyCountryAndCustomBillingCountry(): void
	{
		// Si le pays est vide et billing country = DE, devrait retourner 0 (todo: autres pays)
		$this->assertSame(0.0, BillingTax::getBillingTaxRate(null, null, null, 'DE'));
	}

	/* ===================== getBillingTaxRate() - European Union ===================== */

	public function testGetBillingTaxRateForEUCustomerWithVatNumber(): void
	{
		// Client UE avec numéro TVA -> 0%
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('DE', null, 'DE123456789', 'FR'));
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('IT', null, 'IT123456789', 'FR'));
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('ES', null, 'ES123456789', 'FR'));
	}

	public function testGetBillingTaxRateForEUCustomerWithoutVatNumber(): void
	{
		// Client UE sans numéro TVA -> TVA du pays qui facture (France = 20%)
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('DE', null, null, 'FR'));
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('IT', null, null, 'FR'));
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('ES', null, null, 'FR'));
	}

	public function testGetBillingTaxRateForEUCustomerWithEmptyVatNumber(): void
	{
		// Client UE avec numéro TVA vide -> TVA du pays qui facture
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('DE', null, '', 'FR'));
	}

	/* ===================== getBillingTaxRate() - Outside EU ===================== */

	public function testGetBillingTaxRateForNonEUCustomer(): void
	{
		// Client hors UE -> 0%
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('US', null, null, 'FR'));
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('CH', null, null, 'FR'));
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('GB', null, null, 'FR'));
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('CA', null, null, 'FR'));
	}

	public function testGetBillingTaxRateWhenBillingCountryIsOutsideEU(): void
	{
		// Entité qui facture hors UE -> 0%
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('FR', null, null, 'US'));
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('DE', null, null, 'CH'));
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('US', null, null, 'CA'));
	}

	/* ===================== getBillingTaxRate() - France overseas territories ===================== */

	public function testGetBillingTaxRateForFrenchOverseasTerritories(): void
	{
		// Les territoires français d'outre-mer sont traités comme FR
		// Donc devrait retourner 20% (sauf si code postal spécifique)
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('GP', null, null, 'FR')); // Guadeloupe
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('MQ', null, null, 'FR')); // Martinique
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('GF', null, null, 'FR')); // Guyane
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('RE', null, null, 'FR')); // Réunion
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('YT', null, null, 'FR')); // Mayotte
	}

	public function testGetBillingTaxRateForFrenchOverseasWithZipCode(): void
	{
		// Avec code postal, la TVA spécifique s'applique
		$this->assertSame(8.5, BillingTax::getBillingTaxRate('GP', '97100', null, 'FR')); // Guadeloupe
		$this->assertSame(8.5, BillingTax::getBillingTaxRate('MQ', '97200', null, 'FR')); // Martinique
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('GF', '97300', null, 'FR')); // Guyane
		$this->assertSame(8.5, BillingTax::getBillingTaxRate('RE', '97400', null, 'FR')); // Réunion
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('YT', '97600', null, 'FR')); // Mayotte
	}

	/* ===================== getBillingTaxRate() - Same country ===================== */

	public function testGetBillingTaxRateForSameCountryAsBilling(): void
	{
		// Même pays que le pays de facturation
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR', null, null, 'FR'));
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR', '75001', null, 'FR'));
	}

	/* ===================== getBillingTaxRate() - Complex scenarios ===================== */

	public function testGetBillingTaxRateWithAllParameters(): void
	{
		// Test avec tous les paramètres
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR', '75001', null, 'FR'));
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('DE', '10115', 'DE123456789', 'FR'));
	}

	public function testGetBillingTaxRateForDifferentBillingCountries(): void
	{
		// Test avec différents pays de facturation
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR', '75001', null, 'FR'));
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('DE', '10115', null, 'DE'));
		$this->assertSame(0.0, BillingTax::getBillingTaxRate('IT', '00100', null, 'IT'));
	}

	/* ===================== getBillingTaxRate() - Edge cases ===================== */

	public function testGetBillingTaxRateWithShortZipCode(): void
	{
		// Code postal trop court (moins de 3 caractères)
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR', '75'));
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR', '7'));
	}

	public function testGetBillingTaxRateWithNonDOMTOMZipCode(): void
	{
		// Code postal qui ne correspond pas aux DOM-TOM
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR', '75001'));
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR', '13000'));
		$this->assertSame(20.0, BillingTax::getBillingTaxRate('FR', '69000'));
	}
}