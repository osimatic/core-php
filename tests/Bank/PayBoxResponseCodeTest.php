<?php

declare(strict_types=1);

namespace Tests\Bank;

use Osimatic\Bank\PayBoxResponseCode;
use PHPUnit\Framework\TestCase;

final class PayBoxResponseCodeTest extends TestCase
{
	// ========================================
	// Methods Tests
	// ========================================

	public function testIsSuccess(): void
	{
		$this->assertTrue(PayBoxResponseCode::SUCCESS->isSuccess());

		// Every other case is a failure, including the Visa "approved" code which is not the PayBox success code
		foreach (PayBoxResponseCode::cases() as $case) {
			if (PayBoxResponseCode::SUCCESS !== $case) {
				$this->assertFalse($case->isSuccess(), $case->name);
			}
		}
	}

	public function testIsCardIssuerCode(): void
	{
		$this->assertFalse(PayBoxResponseCode::SUCCESS->isCardIssuerCode());
		$this->assertFalse(PayBoxResponseCode::QUESTION_ANSWER_MISMATCH->isCardIssuerCode()); // 00099, upper bound of the PayBox range
		$this->assertTrue(PayBoxResponseCode::APPROVED->isCardIssuerCode()); // 00100, lower bound of the issuer range
		$this->assertTrue(PayBoxResponseCode::INITIATOR_DOMAIN_INCIDENT->isCardIssuerCode()); // 00199, upper bound

		foreach (PayBoxResponseCode::cases() as $case) {
			$this->assertSame((int) $case->value >= 100, $case->isCardIssuerCode(), $case->name);
			$this->assertLessThanOrEqual(199, (int) $case->value, $case->name);
		}
	}

	public function testGetMessage(): void
	{
		$this->assertSame('Opération réussie', PayBoxResponseCode::SUCCESS->getMessage());
		$this->assertSame('Montant incorrect', PayBoxResponseCode::INVALID_AMOUNT->getMessage());
		$this->assertSame('Carte volée', PayBoxResponseCode::STOLEN_CARD->getMessage());
		$this->assertSame('Incident domaine initiateur.', PayBoxResponseCode::INITIATOR_DOMAIN_INCIDENT->getMessage());

		// Codes sharing the same message
		$this->assertSame(PayBoxResponseCode::CONTACT_CARD_ISSUER->getMessage(), PayBoxResponseCode::CONTACT_CARD_ISSUER_REFERRAL->getMessage());
		$this->assertSame(PayBoxResponseCode::PIN_TRIES_EXCEEDED->getMessage(), PayBoxResponseCode::PIN_ATTEMPTS_EXCEEDED->getMessage());

		// Every case has a non-empty message
		foreach (PayBoxResponseCode::cases() as $case) {
			$this->assertNotSame('', $case->getMessage(), $case->name);
		}
	}

	public function testParse(): void
	{
		// Valid codes
		$this->assertSame(PayBoxResponseCode::SUCCESS, PayBoxResponseCode::parse('00000'));
		$this->assertSame(PayBoxResponseCode::PAYBOX_ERROR, PayBoxResponseCode::parse('00003'));
		$this->assertSame(PayBoxResponseCode::APPROVED, PayBoxResponseCode::parse('00100'));
		$this->assertSame(PayBoxResponseCode::INITIATOR_DOMAIN_INCIDENT, PayBoxResponseCode::parse('00199'));

		// Surrounding whitespace is ignored
		$this->assertSame(PayBoxResponseCode::SUCCESS, PayBoxResponseCode::parse(' 00000 '));

		// Null, empty and unknown codes
		$this->assertNull(PayBoxResponseCode::parse(null));
		$this->assertNull(PayBoxResponseCode::parse(''));
		$this->assertNull(PayBoxResponseCode::parse('99999'));
		$this->assertNull(PayBoxResponseCode::parse('00019')); // gap in the PayBox range
		$this->assertNull(PayBoxResponseCode::parse('0'));
		$this->assertNull(PayBoxResponseCode::parse('abcde'));

		// Round trip for every case
		foreach (PayBoxResponseCode::cases() as $case) {
			$this->assertSame($case, PayBoxResponseCode::parse($case->value), $case->name);
		}
	}
}