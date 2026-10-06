<?php

declare(strict_types=1);

namespace Tests\Bank;

use Osimatic\Bank\PayBoxResponse;
use Osimatic\Bank\PayBoxResponseCode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PayBoxResponseTest extends TestCase
{
	// ========================================
	// Constants Tests
	// ========================================

	public function testConstants(): void
	{
		$this->assertSame(1, PayBoxResponse::_3D_SECURE_VERSION_1);
		$this->assertSame(2, PayBoxResponse::_3D_SECURE_VERSION_2);
	}

	// ========================================
	// Constructor Tests
	// ========================================

	public function testConstructor(): void
	{
		// Default values
		$response = new PayBoxResponse();

		$this->assertNull($response->getReference());
		$this->assertNull($response->getResponseCode());
		$this->assertNull($response->getCallNumber());
		$this->assertNull($response->getTransactionNumber());
		$this->assertNull($response->getAuthorisationNumber());
		$this->assertFalse($response->is3DSecureEnabled());

		// 3D Secure not enabled by default
		$response = new PayBoxResponse();
		$this->assertFalse($response->is3DSecureEnabled());
	}

	// ========================================
	// Static Factory Methods Tests
	// ========================================

	public function testGetFromHttpRequest(): void
	{
		// Query parameters
		$request = Request::create(
			'https://example.com/callback',
			'GET',
			[
				'ref' => 'HTTP-REF-001',
				'response_code' => '00000',
				'call_nb' => '111111',
				'transact_nb' => '222222',
			]
		);

		$response = PayBoxResponse::getFromHttpRequest($request);

		$this->assertInstanceOf(PayBoxResponse::class, $response);
		$this->assertSame('HTTP-REF-001', $response->getReference());
		$this->assertSame(PayBoxResponseCode::SUCCESS, $response->getResponseCode());
		$this->assertSame('111111', $response->getCallNumber());
		$this->assertSame('222222', $response->getTransactionNumber());

		// Post data
		$request = Request::create(
			'https://example.com/callback',
			'POST',
			[
				'ref' => 'POST-REF-001',
				'response_code' => '00001',
			]
		);

		$response = PayBoxResponse::getFromHttpRequest($request);

		$this->assertInstanceOf(PayBoxResponse::class, $response);
		$this->assertSame('POST-REF-001', $response->getReference());
		$this->assertSame(PayBoxResponseCode::AUTHORIZATION_CENTER_CONNECTION_FAILED, $response->getResponseCode());
		$this->assertFalse($response->isSuccess());
	}

	public function testGetFromRequest(): void
	{
		// Complete data
		$data = [
			'ref' => 'REF-001',
			'response_code' => '00000',
			'call_nb' => '123456',
			'transact_nb' => '789012',
			'authorizt_nb' => 'AUTH-001',
			'bc_type' => 'CB',
			'bin6' => '411111',
			'bc_ldigit' => '1111',
			'bc_expdate' => '2512',
		];

		$response = PayBoxResponse::getFromRequest($data);

		$this->assertInstanceOf(PayBoxResponse::class, $response);
		$this->assertSame('REF-001', $response->getReference());
		$this->assertSame(PayBoxResponseCode::SUCCESS, $response->getResponseCode());
		$this->assertSame('123456', $response->getCallNumber());
		$this->assertSame('789012', $response->getTransactionNumber());
		$this->assertSame('AUTH-001', $response->getAuthorisationNumber());
		$this->assertSame('CB', $response->getCardType());
		$this->assertSame('411111********1111', $response->getCardNumber());
		$this->assertSame('1111', $response->getCardLastDigits());

		// Minimal data
		$data = [
			'ref' => 'REF-MIN',
			'response_code' => '00000',
		];

		$response = PayBoxResponse::getFromRequest($data);

		$this->assertInstanceOf(PayBoxResponse::class, $response);
		$this->assertSame('REF-MIN', $response->getReference());
		$this->assertSame(PayBoxResponseCode::SUCCESS, $response->getResponseCode());
		$this->assertNull($response->getCallNumber());
		$this->assertNull($response->getTransactionNumber());

		// 3D Secure version 1
		$data = [
			'ref' => 'REF-3DS1',
			'response_code' => '00000',
			'3ds' => 'O',
			'3ds_auth' => 'Y',
		];

		$response = PayBoxResponse::getFromRequest($data);

		$this->assertTrue($response->is3DSecureEnabled());
		$this->assertSame(PayBoxResponse::_3D_SECURE_VERSION_1, $response->get3DSecureVersion());
		$this->assertSame('Y', $response->get3DSecureAuthentication());

		// 3D Secure version 2
		$data = [
			'ref' => 'REF-3DS2',
			'response_code' => '00000',
			'3ds' => 'O',
			'3ds_auth' => 'Y',
			'3ds_v' => '2',
		];

		$response = PayBoxResponse::getFromRequest($data);

		$this->assertTrue($response->is3DSecureEnabled());
		$this->assertSame(PayBoxResponse::_3D_SECURE_VERSION_2, $response->get3DSecureVersion());
		$this->assertSame('Y', $response->get3DSecureAuthentication());

		// Card hash
		$data = [
			'ref' => 'REF-HASH',
			'response_code' => '00000',
			'card_ref' => 'hash_abc123  2206  ---',
		];

		$response = PayBoxResponse::getFromRequest($data);

		$this->assertSame('hash_abc123', $response->getCardHash());
		$this->assertSame('hash_abc123', $response->getCardReference());

		// Expiration date parsing
		$data = [
			'ref' => 'REF-DATE',
			'response_code' => '00000',
			'bc_expdate' => '2512',
		];

		$response = PayBoxResponse::getFromRequest($data);

		$expiration = $response->getCardExpirationDateTime();
		$this->assertInstanceOf(\DateTime::class, $expiration);
		$this->assertSame('2025', $expiration->format('Y'));
		$this->assertSame('12', $expiration->format('m'));

		// Card types
		$data = [
			'ref' => 'REF-VISA',
			'response_code' => '00000',
			'bc_type' => 'VISA',
		];

		$response = PayBoxResponse::getFromRequest($data);
		$this->assertSame('VISA', $response->getCardType());

		$data = [
			'ref' => 'REF-MC',
			'response_code' => '00000',
			'bc_type' => 'EUROCARD_MASTERCARD',
		];

		$response = PayBoxResponse::getFromRequest($data);
		$this->assertSame('EUROCARD_MASTERCARD', $response->getCardType());

		$data = [
			'ref' => 'REF-AMEX',
			'response_code' => '00000',
			'bc_type' => 'AMEX',
		];

		$response = PayBoxResponse::getFromRequest($data);
		$this->assertSame('AMEX', $response->getCardType());

		$data = [
			'ref' => 'REF-CB',
			'response_code' => '00000',
			'bc_type' => 'CB',
		];

		$response = PayBoxResponse::getFromRequest($data);
		$this->assertSame('CB', $response->getCardType());

		// Empty array
		$response = PayBoxResponse::getFromRequest([]);

		$this->assertInstanceOf(PayBoxResponse::class, $response);
		$this->assertNull($response->getReference());
		$this->assertNull($response->getResponseCode());

		// Card number with missing bin6
		$data = [
			'ref' => 'REF-NO-BIN',
			'response_code' => '00000',
			'bc_ldigit' => '4242',
		];

		$response = PayBoxResponse::getFromRequest($data);
		$this->assertSame('4242', $response->getCardLastDigits());
		$this->assertNull($response->getCardNumber());

		// Unknown response code is not mapped to an enum case
		$response = PayBoxResponse::getFromRequest(['response_code' => '99999']);
		$this->assertNull($response->getResponseCode());
		$this->assertFalse($response->isSuccess());
	}

	// ========================================
	// Status Methods Tests
	// ========================================

	public function testIsSuccess(): void
	{
		// Success code
		$response = new PayBoxResponse();
		$response->setResponseCode(PayBoxResponseCode::SUCCESS);
		$this->assertTrue($response->isSuccess());

		// Failure code
		$response = new PayBoxResponse();
		$response->setResponseCode(PayBoxResponseCode::AUTHORIZATION_CENTER_CONNECTION_FAILED);
		$this->assertFalse($response->isSuccess());

		// Null code
		$response = new PayBoxResponse();
		$this->assertFalse($response->isSuccess());

		// Empty code (parsed to null)
		$response = PayBoxResponse::getFromRequest(['response_code' => '']);
		$this->assertFalse($response->isSuccess());

		// Authorization refused
		$response = new PayBoxResponse();
		$response->setResponseCode(PayBoxResponseCode::PAYBOX_ERROR);
		$this->assertFalse($response->isSuccess());

		// Invalid merchant
		$response = new PayBoxResponse();
		$response->setResponseCode(PayBoxResponseCode::INVALID_EXPIRATION_DATE);
		$this->assertFalse($response->isSuccess());
	}

	// ========================================
	// Interface Methods Tests
	// ========================================

	public function testGetOrderReference(): void
	{
		$response = new PayBoxResponse();
		$response->setReference('ORDER-12345');

		$this->assertSame('ORDER-12345', $response->getOrderReference());
	}

	public function testGetCardReference(): void
	{
		$response = new PayBoxResponse();
		$response->setCardHash('card_hash_value');

		$this->assertSame('card_hash_value', $response->getCardReference());
	}

	// ========================================
	// Getters & Setters Tests
	// ========================================

	public function testGetReference(): void
	{
		$response = new PayBoxResponse();
		$response->setReference('REF-12345');

		$this->assertSame('REF-12345', $response->getReference());
	}

	public function testSetReference(): void
	{
		$response = new PayBoxResponse();
		$response->setReference('REF-001');
		$response->setReference(null);

		$this->assertNull($response->getReference());
	}

	public function testGetResponseCode(): void
	{
		$response = new PayBoxResponse();
		$response->setResponseCode(PayBoxResponseCode::SUCCESS);

		$this->assertSame(PayBoxResponseCode::SUCCESS, $response->getResponseCode());
	}

	public function testSetResponseCode(): void
	{
		$response = new PayBoxResponse();
		$response->setResponseCode(PayBoxResponseCode::SUCCESS);
		$response->setResponseCode(null);

		$this->assertNull($response->getResponseCode());
	}

	public function testGetCallNumber(): void
	{
		$response = new PayBoxResponse();
		$response->setCallNumber('1234567890');

		$this->assertSame('1234567890', $response->getCallNumber());
	}

	public function testSetCallNumber(): void
	{
		$response = new PayBoxResponse();
		$response->setCallNumber('1234567890');

		$this->assertSame('1234567890', $response->getCallNumber());
	}

	public function testGetTransactionNumber(): void
	{
		$response = new PayBoxResponse();
		$response->setTransactionNumber('9876543210');

		$this->assertSame('9876543210', $response->getTransactionNumber());
	}

	public function testSetTransactionNumber(): void
	{
		$response = new PayBoxResponse();
		$response->setTransactionNumber('9876543210');

		$this->assertSame('9876543210', $response->getTransactionNumber());
	}

	public function testGetAuthorisationNumber(): void
	{
		$response = new PayBoxResponse();
		$response->setAuthorisationNumber('AUTH-001');

		$this->assertSame('AUTH-001', $response->getAuthorisationNumber());
	}

	public function testSetAuthorisationNumber(): void
	{
		$response = new PayBoxResponse();
		$response->setAuthorisationNumber('AUTH-001');

		$this->assertSame('AUTH-001', $response->getAuthorisationNumber());
	}

	public function testGetCardType(): void
	{
		$response = new PayBoxResponse();
		$response->setCardType('VISA');

		$this->assertSame('VISA', $response->getCardType());
	}

	public function testSetCardType(): void
	{
		$response = new PayBoxResponse();
		$response->setCardType('VISA');
		$this->assertSame('VISA', $response->getCardType());

		$response->setCardType('MASTERCARD');
		$this->assertSame('MASTERCARD', $response->getCardType());
	}

	public function testGetCardNumber(): void
	{
		$response = new PayBoxResponse();
		$response->setCardNumber('4111********1111');

		$this->assertSame('4111********1111', $response->getCardNumber());
	}

	public function testSetCardNumber(): void
	{
		$response = new PayBoxResponse();
		$response->setCardNumber('4111********1111');

		$this->assertSame('4111********1111', $response->getCardNumber());
	}

	public function testGetCardLastDigits(): void
	{
		$response = new PayBoxResponse();
		$response->setCardLastDigits('1234');

		$this->assertSame('1234', $response->getCardLastDigits());
	}

	public function testSetCardLastDigits(): void
	{
		$response = new PayBoxResponse();
		$response->setCardLastDigits('1234');

		$this->assertSame('1234', $response->getCardLastDigits());
	}

	public function testGetCardExpirationDateTime(): void
	{
		$response = new PayBoxResponse();
		$date = new \DateTime('2025-12-31');
		$response->setCardExpirationDateTime($date);

		$this->assertSame($date, $response->getCardExpirationDateTime());
	}

	public function testSetCardExpirationDateTime(): void
	{
		$response = new PayBoxResponse();
		$date = new \DateTime('2025-12-31');
		$response->setCardExpirationDateTime($date);
		$response->setCardExpirationDateTime(null);

		$this->assertNull($response->getCardExpirationDateTime());
	}

	public function testGetCardHash(): void
	{
		$response = new PayBoxResponse();
		$response->setCardHash('abc123hash');

		$this->assertSame('abc123hash', $response->getCardHash());
	}

	public function testSetCardHash(): void
	{
		$response = new PayBoxResponse();
		$response->setCardHash('hash123');
		$response->setCardHash(null);

		$this->assertNull($response->getCardHash());
	}

	public function testIs3DSecureEnabled(): void
	{
		$response = new PayBoxResponse();
		$response->set3DSecureEnabled(true);

		$this->assertTrue($response->is3DSecureEnabled());
	}

	public function testSet3DSecureEnabled(): void
	{
		$response = new PayBoxResponse();
		$response->set3DSecureEnabled(true);
		$response->set3DSecureEnabled(false);

		$this->assertFalse($response->is3DSecureEnabled());
	}

	public function testGet3DSecureAuthentication(): void
	{
		$response = new PayBoxResponse();
		$response->set3DSecureAuthentication('Y');

		$this->assertSame('Y', $response->get3DSecureAuthentication());
	}

	public function testSet3DSecureAuthentication(): void
	{
		$response = new PayBoxResponse();

		$response->set3DSecureAuthentication('Y');
		$this->assertSame('Y', $response->get3DSecureAuthentication());

		$response->set3DSecureAuthentication('N');
		$this->assertSame('N', $response->get3DSecureAuthentication());

		$response->set3DSecureAuthentication('U');
		$this->assertSame('U', $response->get3DSecureAuthentication());

		$response->set3DSecureAuthentication('A');
		$this->assertSame('A', $response->get3DSecureAuthentication());
	}

	public function testGet3DSecureVersion(): void
	{
		$response = new PayBoxResponse();
		$response->set3DSecureVersion(PayBoxResponse::_3D_SECURE_VERSION_2);

		$this->assertSame(PayBoxResponse::_3D_SECURE_VERSION_2, $response->get3DSecureVersion());
	}

	public function testSet3DSecureVersion(): void
	{
		$response = new PayBoxResponse();
		$response->set3DSecureVersion(PayBoxResponse::_3D_SECURE_VERSION_1);
		$this->assertSame(PayBoxResponse::_3D_SECURE_VERSION_1, $response->get3DSecureVersion());

		$response->set3DSecureVersion(PayBoxResponse::_3D_SECURE_VERSION_2);
		$response->set3DSecureVersion(null);
		$this->assertNull($response->get3DSecureVersion());
	}

	// ========================================
	// Deprecated Methods Tests
	// ========================================

	public function testGetAuthorizationNumber(): void
	{
		$response = new PayBoxResponse();
		$response->setAuthorisationNumber('AUTH-123');

		$this->assertSame('AUTH-123', $response->getAuthorizationNumber());
	}

	public function testSetAuthorizationNumber(): void
	{
		$response = new PayBoxResponse();
		$response->setAuthorizationNumber('AUTH-456');

		$this->assertSame('AUTH-456', $response->getAuthorisationNumber());
	}
}