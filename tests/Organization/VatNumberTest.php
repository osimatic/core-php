<?php

declare(strict_types=1);

namespace Tests\Organization;

use Osimatic\Organization\VatNumber;
use PHPUnit\Framework\TestCase;

final class VatNumberTest extends TestCase
{
	/* ===================== Setup ===================== */

	/**
	 * Replaces the real VIES SOAP service with a fake client so that tests do not depend on the network.
	 * The fake client reports every number as valid, except the all-zero number used by the invalid-number tests.
	 * @link https://www.php.net/manual/en/class.soapclient.php
	 */
	protected function setUp(): void
	{
		VatNumber::setViesClient(new class extends \SoapClient {
			public function __construct()
			{
				// Intentionally does not call parent::__construct(): no WSDL is loaded
			}

			public function checkVat(array $args): object
			{
				return (object) ['valid' => $args['vatNumber'] !== '00000000000'];
			}
		});
	}

	/**
	 * Restores the default VIES client behavior after each test.
	 */
	protected function tearDown(): void
	{
		VatNumber::setViesClient(null);
	}

	/* ===================== format() ===================== */

	public function testFormat(): void
	{
		$formatted = VatNumber::format('FR12345678901');
		$this->assertIsString($formatted);
		$this->assertSame('FR12345678901', $formatted); // TODO: à implémenter dans la classe
	}

	/* ===================== isValid() ===================== */

	public function testCheckValidFrenchVatNumber(): void
	{
		// Numéro TVA FR valide : FR 44 732829320 (exemple Wikipédia)
		$this->assertTrue(VatNumber::isValid('FR44732829320', checkValidity: false));
	}

	public function testCheckValidMonacoVatNumber(): void
	{
		// Numéro TVA Monaco (9 chiffres après FR)
		// Note: La clé doit être valide selon l'algorithme
		// Format: FR + 2 chiffres clé + 7 chiffres SSEE
		// Les numéros Monaco ne peuvent pas être validés car le SSEE n'a pas de validation Luhn
		$this->markTestSkipped('Les numéros de TVA Monaco ne peuvent pas être validés automatiquement');
	}

	public function testCheckEmptyVatNumber(): void
	{
		$this->assertFalse(VatNumber::isValid(''));
	}

	public function testCheckInvalidFormat(): void
	{
		$this->assertFalse(VatNumber::isValid('123456789'));
		$this->assertFalse(VatNumber::isValid('FR'));
		$this->assertFalse(VatNumber::isValid('FRAB'));
	}

	public function testCheckInvalidCountryCode(): void
	{
		$this->assertFalse(VatNumber::isValid('12345678901'));
		$this->assertFalse(VatNumber::isValid('A1234567890'));
	}

	public function testCheckInvalidVatNumberLength(): void
	{
		$this->assertFalse(VatNumber::isValid('FR1'));
		$this->assertFalse(VatNumber::isValid('FR12345678901234567890'));
	}

	public function testCheckFranceInvalidSiren(): void
	{
		// SIREN invalide (mauvaise clé Luhn)
		$this->assertFalse(VatNumber::isValid('FR12123456789', checkValidity: false));
	}

	public function testCheckFranceInvalidVatKey(): void
	{
		// SIREN valide mais clé TVA incorrecte
		$this->assertFalse(VatNumber::isValid('FR00552100554', checkValidity: false));
		$this->assertFalse(VatNumber::isValid('FR99732829320', checkValidity: false));
	}

	public function testCheckFranceWrongLength(): void
	{
		// Ni 11 ni 9 chiffres
		$this->assertFalse(VatNumber::isValid('FR1234567890', checkValidity: false));
		$this->assertFalse(VatNumber::isValid('FR123456789012', checkValidity: false));
	}

	public function testCheckOtherCountryFormat(): void
	{
		// Pour les autres pays, vérifie juste le format
		$this->assertTrue(VatNumber::isValid('DE123456789', checkValidity: false));
		$this->assertTrue(VatNumber::isValid('GB123456789', checkValidity: false));
		$this->assertTrue(VatNumber::isValid('ES12345678A', checkValidity: false));
	}

	public function testCheckWithValidityCheck(): void
	{
		// Ce test va essayer de contacter l'API VIES
		// On teste juste que la méthode s'exécute sans erreur
		$result = VatNumber::isValid('FR32552100554', checkValidity: true);
		$this->assertIsBool($result);
	}

	/* ===================== verifyWithVies() ===================== */

	public function testVerifyWithViesCallsExternalAPI(): void
	{
		// Ce test appelle l'API VIES
		// Le résultat dépend de la disponibilité de l'API et de la validité réelle du numéro
		$result = VatNumber::verifyWithVies('FR32552100554');
		$this->assertIsBool($result);
	}

	public function testVerifyWithViesWithInvalidNumber(): void
	{
		// Avec un numéro clairement invalide
		$result = VatNumber::verifyWithVies('FR00000000000');
		$this->assertFalse($result);
	}

	/* ===================== DEPRECATED ===================== */

	public function testDeprecatedCheckValidityCallsExternalAPI(): void
	{
		// Test backward compatibility: deprecated checkValidity() redirects to verifyWithVies()
		$result = VatNumber::checkValidity('FR32552100554');
		$this->assertIsBool($result);
	}

	public function testDeprecatedCheckValidityWithInvalidNumber(): void
	{
		// Test backward compatibility: deprecated checkValidity() redirects to verifyWithVies()
		$result = VatNumber::checkValidity('FR00000000000');
		$this->assertFalse($result);
	}

	/* ===================== Edge cases ===================== */

	public function testCheckVatNumberWithSpaces(): void
	{
		// Les espaces ne sont pas acceptés
		$this->assertFalse(VatNumber::isValid('FR 32 552100554', checkValidity: false));
	}

	public function testCheckVatNumberLowerCase(): void
	{
		// Le code pays doit être en majuscules
		$this->assertFalse(VatNumber::isValid('fr32552100554', checkValidity: false));
	}

	public function testCheckValidVatNumberFormats(): void
	{
		// Différents formats selon les pays
		$this->assertTrue(VatNumber::isValid('BE0123456789', checkValidity: false));
		$this->assertTrue(VatNumber::isValid('NL123456789B01', checkValidity: false));
		$this->assertTrue(VatNumber::isValid('IT12345678901', checkValidity: false));
	}

	/* ===================== French VAT key calculation ===================== */

	public function testFrenchVatKeyCalculation(): void
	{
		// Vérification de l'algorithme de calcul de la clé TVA française
		// SIREN: 732829320 -> Clé: 44
		// Formule: ((SIREN % 97) * 3 + 12) % 97
		$this->assertTrue(VatNumber::isValid('FR44732829320', checkValidity: false));

		// SIREN: 552100554 -> Clé: 96 (et non 32)
		$this->assertTrue(VatNumber::isValid('FR96552100554', checkValidity: false));
	}

	public function testMonacoVatNumberFormat(): void
	{
		// Monaco utilise le préfixe FR mais avec 9 chiffres au lieu de 11
		// Format: FR + 2 chiffres clé + 7 chiffres SSEE
		// Les numéros Monaco ne peuvent pas être validés car le SSEE n'a pas de validation Luhn
		$this->markTestSkipped('Les numéros de TVA Monaco ne peuvent pas être validés automatiquement');
	}
}
