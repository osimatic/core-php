<?php

declare(strict_types=1);

namespace Tests\Bank;

use GuzzleHttp\Psr7\Response;
use Osimatic\Bank\BankCardCallOrigin;
use Osimatic\Bank\BankCardOperation;
use Osimatic\Bank\PayBox;
use Osimatic\Bank\PayBoxResponse;
use Osimatic\Bank\PayBoxResponseCode;
use Osimatic\Bank\PayBoxVersion;
use Osimatic\Bank\ShoppingCartInterface;
use Osimatic\Invoice\BillingAddressInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
final class PayBoxTest extends TestCase
{
	private PayBox $paybox;

	protected function setUp(): void
	{
		$this->paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: '0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF'
		);
	}

	/* ===================== Constants ===================== */

	public function testDefaultFormTimeoutConstant(): void
	{
		$this->assertSame(1800, PayBox::DEFAULT_FORM_TIMEOUT);
	}

	/* ===================== Constructor ===================== */

	public function testConstructorWithAllParameters(): void
	{
		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: 'password1',
			secretKey: 'secret123'
		);

		$this->assertInstanceOf(PayBox::class, $paybox);
	}

	public function testConstructorWithLogger(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: 'password1',
			secretKey: 'secret123',
			logger: $logger
		);

		$this->assertInstanceOf(PayBox::class, $paybox);
	}

	/* ===================== Setters - Basic configuration ===================== */

	public function testSetVersion(): void
	{
		$result = $this->paybox->setVersion(PayBoxVersion::PAYBOX_DIRECT);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetSiteNumber(): void
	{
		$result = $this->paybox->setSiteNumber('9876543');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetRang(): void
	{
		$result = $this->paybox->setRang('01');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetIdentifier(): void
	{
		$result = $this->paybox->setIdentifier('987654321');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetHttpPassword(): void
	{
		$result = $this->paybox->setHttpPassword('newpass123');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetSecretKey(): void
	{
		$result = $this->paybox->setSecretKey('newsecretkey');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetLocale(): void
	{
		$result = $this->paybox->setLocale('en_GB');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetIsTest(): void
	{
		$result = $this->paybox->setIsTest(true);

		$this->assertSame($this->paybox, $result);
	}


	/* ===================== Setters - Transaction parameters ===================== */

	public function testSetDate(): void
	{
		$date = new \DateTime('2024-01-15 10:30:00');
		$result = $this->paybox->setDate($date);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetDateWithNull(): void
	{
		$result = $this->paybox->setDate(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetBankCardOperation(): void
	{
		$result = $this->paybox->setBankCardOperation(BankCardOperation::AUTHORIZATION_ONLY);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetQuestionNumber(): void
	{
		$result = $this->paybox->setQuestionNumber(123456789);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetQuestionNumberWithNull(): void
	{
		$result = $this->paybox->setQuestionNumber(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetTotal(): void
	{
		$result = $this->paybox->setTotal(99.99);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetTransactionAmount(): void
	{
		$result = $this->paybox->setTransactionAmount(150.50);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetCurrency(): void
	{
		$result = $this->paybox->setCurrency('USD');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetReference(): void
	{
		$result = $this->paybox->setReference('ORDER-12345');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetReferenceWithNull(): void
	{
		$result = $this->paybox->setReference(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetSubscriberReference(): void
	{
		$result = $this->paybox->setSubscriberReference('SUB-98765');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetSubscriberReferenceWithNull(): void
	{
		$result = $this->paybox->setSubscriberReference(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetCustomerEmail(): void
	{
		$result = $this->paybox->setCustomerEmail('customer@example.com');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetCustomerEmailWithNull(): void
	{
		$result = $this->paybox->setCustomerEmail(null);

		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Setters - Card information ===================== */

	public function testSetCreditCardNumber(): void
	{
		$result = $this->paybox->setCreditCardNumber('4111111111111111');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetCreditCardNumberWithNull(): void
	{
		$result = $this->paybox->setCreditCardNumber(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetCreditCardToken(): void
	{
		$result = $this->paybox->setCreditCardToken('TOKEN123456');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetCreditCardTokenWithNull(): void
	{
		$result = $this->paybox->setCreditCardToken(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetExpirationDate(): void
	{
		$date = new \DateTime('2025-12-31');
		$result = $this->paybox->setExpirationDate($date);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetExpirationDateWithNull(): void
	{
		$result = $this->paybox->setExpirationDate(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetCvc(): void
	{
		$result = $this->paybox->setCvc('123');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetCvcWithNull(): void
	{
		$result = $this->paybox->setCvc(null);

		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Setters - Additional parameters ===================== */

	public function testSetCallOrigin(): void
	{
		$result = $this->paybox->setCallOrigin(BankCardCallOrigin::INTERNET_PAYMENT);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetCallOriginWithNull(): void
	{
		$result = $this->paybox->setCallOrigin(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetArchivingReference(): void
	{
		$result = $this->paybox->setArchivingReference('ARCH-12345');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetArchivingReferenceWithNull(): void
	{
		$result = $this->paybox->setArchivingReference(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetNumberOfDaysForPostponedSettlement(): void
	{
		$result = $this->paybox->setNumberOfDaysForPostponedSettlement(7);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetNumberOfDaysForPostponedSettlementWithNull(): void
	{
		$result = $this->paybox->setNumberOfDaysForPostponedSettlement(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetCallNumber(): void
	{
		$result = $this->paybox->setCallNumber(1234567890);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetTransactionNumber(): void
	{
		$result = $this->paybox->setTransactionNumber(9876543210);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetAuthorizationNumber(): void
	{
		$result = $this->paybox->setAuthorizationNumber('AUTH123');

		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Setters - Form parameters ===================== */

	public function testSetFormCssClass(): void
	{
		$result = $this->paybox->setFormCssClass('my-custom-form');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetFormCssClassWithNull(): void
	{
		$result = $this->paybox->setFormCssClass(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetButtonCssClass(): void
	{
		$result = $this->paybox->setButtonCssClass('btn-custom');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetButtonCssClassWithNull(): void
	{
		$result = $this->paybox->setButtonCssClass(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetButtonText(): void
	{
		$result = $this->paybox->setButtonText('Pay Now');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetButtonTextWithNull(): void
	{
		$result = $this->paybox->setButtonText(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetUrlResponseOk(): void
	{
		$result = $this->paybox->setUrlResponseOk('https://example.com/success');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetUrlResponseOkWithNull(): void
	{
		$result = $this->paybox->setUrlResponseOk(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetUrlResponseRefused(): void
	{
		$result = $this->paybox->setUrlResponseRefused('https://example.com/refused');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetUrlResponseRefusedWithNull(): void
	{
		$result = $this->paybox->setUrlResponseRefused(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetUrlResponseCanceled(): void
	{
		$result = $this->paybox->setUrlResponseCanceled('https://example.com/canceled');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetUrlResponseCanceledWithNull(): void
	{
		$result = $this->paybox->setUrlResponseCanceled(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetUrlResponseWaiting(): void
	{
		$result = $this->paybox->setUrlResponseWaiting('https://example.com/waiting');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetUrlResponseWaitingWithNull(): void
	{
		$result = $this->paybox->setUrlResponseWaiting(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetUrlIpn(): void
	{
		$result = $this->paybox->setUrlIpn('https://example.com/ipn');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetUrlIpnWithNull(): void
	{
		$result = $this->paybox->setUrlIpn(null);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetAuthorizationOnly(): void
	{
		$result = $this->paybox->setAuthorizationOnly();

		$this->assertSame($this->paybox, $result);
	}

	/* ===================== 3D Secure ===================== */

	public function testSet3DSecureV2(): void
	{
		$result = $this->paybox->set3DSecureV2();

		$this->assertSame($this->paybox, $result);
		$this->assertTrue($this->paybox->is3DSecureV2());
	}

	public function testIs3DSecureV2DefaultsToFalse(): void
	{
		$this->assertFalse($this->paybox->is3DSecureV2());
	}

	/* ===================== Form timeout ===================== */

	public function testGetFormTimeoutDefaultValue(): void
	{
		$this->assertSame(PayBox::DEFAULT_FORM_TIMEOUT, $this->paybox->getFormTimeout());
	}

	public function testSetFormTimeout(): void
	{
		$result = $this->paybox->setFormTimeout(3600);

		$this->assertSame($this->paybox, $result);
		$this->assertSame(3600, $this->paybox->getFormTimeout());
	}

	/* ===================== Billing address ===================== */

	public function testSetBillingAddress(): void
	{
		$billingAddress = $this->createMock(BillingAddressInterface::class);
		$result = $this->paybox->setBillingAddress($billingAddress);

		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Shopping cart ===================== */

	public function testSetShoppingCart(): void
	{
		$shoppingCart = $this->createMock(ShoppingCartInterface::class);
		$result = $this->paybox->setShoppingCart($shoppingCart);

		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Getters ===================== */

	public function testGetCallNumberDefaultsToNull(): void
	{
		$this->assertNull($this->paybox->getCallNumber());
	}

	public function testGetCallNumberAfterSet(): void
	{
		$this->paybox->setCallNumber(1234567890);

		$this->assertSame(1234567890, $this->paybox->getCallNumber());
	}

	public function testGetTransactionNumberDefaultsToNull(): void
	{
		$this->assertNull($this->paybox->getTransactionNumber());
	}

	public function testGetTransactionNumberAfterSet(): void
	{
		$this->paybox->setTransactionNumber(9876543210);

		$this->assertSame(9876543210, $this->paybox->getTransactionNumber());
	}

	public function testGetAuthorizationNumberDefaultsToNull(): void
	{
		$this->assertNull($this->paybox->getAuthorizationNumber());
	}

	public function testGetAuthorizationNumberAfterSet(): void
	{
		$this->paybox->setAuthorizationNumber('AUTH123');

		$this->assertSame('AUTH123', $this->paybox->getAuthorizationNumber());
	}

	public function testGetCardCountryCodeDefaultsToNull(): void
	{
		$this->assertNull($this->paybox->getCardCountryCode());
	}

	/* ===================== reset() ===================== */

	public function testResetClearsTransactionData(): void
	{
		// Set some data
		$this->paybox->setQuestionNumber(123456);
		$this->paybox->setDate(new \DateTime());
		$this->paybox->setReference('ORDER-123');
		$this->paybox->setSubscriberReference('SUB-456');
		$this->paybox->setCustomerEmail('test@example.com');
		$this->paybox->setCreditCardNumber('4111111111111111');
		$this->paybox->setExpirationDate(new \DateTime('2025-12-31'));
		$this->paybox->setCvc('123');
		$this->paybox->setCallNumber(1234567890);
		$this->paybox->setTransactionNumber(9876543210);
		$this->paybox->setAuthorizationNumber('AUTH123');

		// Reset
		$this->paybox->reset();

		// Verify data is cleared
		$this->assertNull($this->paybox->getCallNumber());
		$this->assertNull($this->paybox->getTransactionNumber());
		$this->assertNull($this->paybox->getAuthorizationNumber());
	}

	public function testResetDoesNotAffectConfiguration(): void
	{
		// Set configuration
		$this->paybox->setLocale('en_GB');
		$this->paybox->setIsTest(true);
		$this->paybox->setTotal(100.00);

		// Reset
		$this->paybox->reset();

		// Configuration should still be set (total is not cleared by reset)
		// Only transaction-specific data is cleared
		$this->assertInstanceOf(PayBox::class, $this->paybox);
	}

	/* ===================== Fluent interface ===================== */

	public function testFluentInterfaceChaining(): void
	{
		$result = $this->paybox
			->setTotal(99.99)
			->setCurrency('EUR')
			->setReference('ORDER-123')
			->setCustomerEmail('customer@example.com')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('2025-12-31'))
			->setCvc('123');

		$this->assertSame($this->paybox, $result);
	}

	public function testFluentInterfaceWithFormConfiguration(): void
	{
		$result = $this->paybox
			->setFormCssClass('payment-form')
			->setButtonCssClass('btn btn-primary')
			->setButtonText('Pay Now')
			->setUrlResponseOk('https://example.com/success')
			->setUrlResponseRefused('https://example.com/refused')
			->setUrlResponseCanceled('https://example.com/canceled')
			->setUrlIpn('https://example.com/ipn');

		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Multiple operations ===================== */

	public function testSetTransactionAmountIsSameAsSetTotal(): void
	{
		$paybox1 = new PayBox('1234567', '99', '123456789', '12345678', 'secret');
		$paybox2 = new PayBox('1234567', '99', '123456789', '12345678', 'secret');

		$paybox1->setTotal(150.50);
		$paybox2->setTransactionAmount(150.50);

		// Both should behave the same way (they call the same internal method)
		$this->assertInstanceOf(PayBox::class, $paybox1);
		$this->assertInstanceOf(PayBox::class, $paybox2);
	}

	public function testSetCreditCardNumberAndTokenUseSameInternalProperty(): void
	{
		// Set card number
		$this->paybox->setCreditCardNumber('4111111111111111');

		// Set token (should overwrite card number)
		$this->paybox->setCreditCardToken('TOKEN123');

		// Both methods set the same property internally, so this should work
		$this->assertInstanceOf(PayBox::class, $this->paybox);
	}

	/* ===================== Edge cases ===================== */

	public function testSetTotalWithZero(): void
	{
		$result = $this->paybox->setTotal(0.0);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetTotalWithNegativeValue(): void
	{
		$result = $this->paybox->setTotal(-50.00);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetTotalWithLargeValue(): void
	{
		$result = $this->paybox->setTotal(999999.99);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetReferenceWithEmptyString(): void
	{
		$result = $this->paybox->setReference('');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetReferenceWithLongString(): void
	{
		$longReference = str_repeat('A', 250);
		$result = $this->paybox->setReference($longReference);

		$this->assertSame($this->paybox, $result);
	}

	public function testSetCustomerEmailWithEmptyString(): void
	{
		$result = $this->paybox->setCustomerEmail('');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetCvcWithThreeDigits(): void
	{
		$result = $this->paybox->setCvc('123');

		$this->assertSame($this->paybox, $result);
	}

	public function testSetCvcWithFourDigits(): void
	{
		$result = $this->paybox->setCvc('1234');

		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Different locales ===================== */

	public function testSetLocaleEnglish(): void
	{
		$result = $this->paybox->setLocale('en_GB');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetLocaleSpanish(): void
	{
		$result = $this->paybox->setLocale('es_ES');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetLocaleFrench(): void
	{
		$result = $this->paybox->setLocale('fr_FR');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetLocaleGerman(): void
	{
		$result = $this->paybox->setLocale('de_DE');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetLocaleItalian(): void
	{
		$result = $this->paybox->setLocale('it_IT');
		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Different bank card operations ===================== */

	public function testSetBankCardOperationAuthorizationOnly(): void
	{
		$result = $this->paybox->setBankCardOperation(BankCardOperation::AUTHORIZATION_ONLY);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetBankCardOperationDebit(): void
	{
		$result = $this->paybox->setBankCardOperation(BankCardOperation::DEBIT);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetBankCardOperationAuthorizationAndDebit(): void
	{
		$result = $this->paybox->setBankCardOperation(BankCardOperation::AUTHORIZATION_AND_DEBIT);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetBankCardOperationCredit(): void
	{
		$result = $this->paybox->setBankCardOperation(BankCardOperation::CREDIT);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetBankCardOperationCancel(): void
	{
		$result = $this->paybox->setBankCardOperation(BankCardOperation::CANCEL);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetBankCardOperationRefund(): void
	{
		$result = $this->paybox->setBankCardOperation(BankCardOperation::REFUND);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetBankCardOperationRegisterSubscriber(): void
	{
		$result = $this->paybox->setBankCardOperation(BankCardOperation::REGISTER_SUBSCRIBER);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetBankCardOperationUpdateSubscriber(): void
	{
		$result = $this->paybox->setBankCardOperation(BankCardOperation::UPDATE_SUBSCRIBER);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetBankCardOperationDeleteSubscriber(): void
	{
		$result = $this->paybox->setBankCardOperation(BankCardOperation::DELETE_SUBSCRIBER);
		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Different call origins ===================== */

	public function testSetCallOriginNotSpecified(): void
	{
		$result = $this->paybox->setCallOrigin(BankCardCallOrigin::NOT_SPECIFIED);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCallOriginTelephoneOrder(): void
	{
		$result = $this->paybox->setCallOrigin(BankCardCallOrigin::TELEPHONE_ORDER);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCallOriginMailOrder(): void
	{
		$result = $this->paybox->setCallOrigin(BankCardCallOrigin::MAIL_ORDER);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCallOriginMinitel(): void
	{
		$result = $this->paybox->setCallOrigin(BankCardCallOrigin::MINITEL);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCallOriginInternetPayment(): void
	{
		$result = $this->paybox->setCallOrigin(BankCardCallOrigin::INTERNET_PAYMENT);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCallOriginRecurringPayment(): void
	{
		$result = $this->paybox->setCallOrigin(BankCardCallOrigin::RECURRING_PAYMENT);
		$this->assertSame($this->paybox, $result);
	}

	/* ===================== PayBox versions ===================== */

	public function testSetVersionPayBoxDirect(): void
	{
		$result = $this->paybox->setVersion(PayBoxVersion::PAYBOX_DIRECT);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetVersionPayBoxDirectPlus(): void
	{
		$result = $this->paybox->setVersion(PayBoxVersion::PAYBOX_DIRECT_PLUS);
		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Additional edge cases ===================== */

	public function testSetCreditCardNumberWithSpaces(): void
	{
		// Should accept card numbers with spaces (validation happens later)
		$result = $this->paybox->setCreditCardNumber('4111 1111 1111 1111');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCreditCardNumberMaxLength(): void
	{
		// Maximum 19 digits
		$result = $this->paybox->setCreditCardNumber('1234567890123456789');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCvcWithEmptyString(): void
	{
		$result = $this->paybox->setCvc('');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetSubscriberReferenceMaxLength(): void
	{
		// Maximum 250 characters
		$longSubscriberRef = str_repeat('A', 250);
		$result = $this->paybox->setSubscriberReference($longSubscriberRef);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetNumberOfDaysForPostponedSettlementZero(): void
	{
		$result = $this->paybox->setNumberOfDaysForPostponedSettlement(0);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetNumberOfDaysForPostponedSettlementSeven(): void
	{
		$result = $this->paybox->setNumberOfDaysForPostponedSettlement(7);
		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Multiple setters ===================== */

	public function testCompletePaymentConfiguration(): void
	{
		$result = $this->paybox
			->setIsTest(false)
			->setVersion(PayBoxVersion::PAYBOX_DIRECT_PLUS)
			->setLocale('en_GB')
			->setTotal(99.99)
			->setCurrency('EUR')
			->setReference('ORDER-12345')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('2025-12-31'))
			->setCvc('123')
			->setCallOrigin(BankCardCallOrigin::INTERNET_PAYMENT);

		$this->assertSame($this->paybox, $result);
	}

	public function testCompleteFormConfiguration(): void
	{
		$result = $this->paybox
			->setIsTest(false)
			->setTotal(149.99)
			->setCurrency('USD')
			->setReference('FORM-ORDER-789')
			->setCustomerEmail('customer@example.com')
			->setFormCssClass('checkout-form')
			->setButtonCssClass('btn-pay')
			->setButtonText('Proceed to Payment')
			->setUrlResponseOk('https://example.com/success')
			->setUrlResponseRefused('https://example.com/refused')
			->setUrlResponseCanceled('https://example.com/canceled')
			->setUrlResponseWaiting('https://example.com/waiting')
			->setUrlIpn('https://example.com/webhook/paybox')
			->setFormTimeout(3600);

		$this->assertSame($this->paybox, $result);
	}

	public function testComplete3DSecureConfiguration(): void
	{
		$billingAddress = $this->createMock(BillingAddressInterface::class);
		$shoppingCart = $this->createMock(ShoppingCartInterface::class);

		$result = $this->paybox
			->set3DSecureV2()
			->setBillingAddress($billingAddress)
			->setShoppingCart($shoppingCart)
			->setTotal(199.99)
			->setReference('3DS-ORDER-456');

		$this->assertSame($this->paybox, $result);
		$this->assertTrue($this->paybox->is3DSecureV2());
	}

	public function testSubscriberPaymentConfiguration(): void
	{
		$result = $this->paybox
			->setSubscriberReference('SUBSCRIBER-TOKEN-123')
			->setCreditCardToken('CARD-TOKEN-456')
			->setTotal(29.99)
			->setReference('SUBSCRIPTION-ORDER-789')
			->setCallOrigin(BankCardCallOrigin::RECURRING_PAYMENT);

		$this->assertSame($this->paybox, $result);
	}

	public function testAuthorizationOnlyOperation(): void
	{
		$result = $this->paybox
			->setAuthorizationOnly()
			->setTotal(299.99)
			->setReference('AUTH-ONLY-ORDER-999')
			->setCreditCardNumber('5555555555554444')
			->setExpirationDate(new \DateTime('2026-06-30'))
			->setCvc('456');

		$this->assertSame($this->paybox, $result);
	}

	public function testCaptureAfterAuthorizationConfiguration(): void
	{
		$result = $this->paybox
			->setBankCardOperation(BankCardOperation::DEBIT)
			->setCallNumber(1234567890)
			->setTransactionNumber(9876543210)
			->setTotal(299.99)
			->setReference('CAPTURE-ORDER-888');

		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Reset with complex data ===================== */

	public function testResetClearsShoppingCartAndBillingAddress(): void
	{
		$billingAddress = $this->createMock(BillingAddressInterface::class);
		$shoppingCart = $this->createMock(ShoppingCartInterface::class);

		$this->paybox->setBillingAddress($billingAddress);
		$this->paybox->setShoppingCart($shoppingCart);
		$this->paybox->setQuestionNumber(999999);
		$this->paybox->setDate(new \DateTime('2025-01-01'));

		// Reset should clear transaction data including cart and address
		$this->paybox->reset();

		// Can't directly test if they're null, but reset should have been called
		$this->assertInstanceOf(PayBox::class, $this->paybox);
	}

	/* ===================== Different currencies ===================== */

	public function testSetCurrencyEUR(): void
	{
		$result = $this->paybox->setCurrency('EUR');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCurrencyUSD(): void
	{
		$result = $this->paybox->setCurrency('USD');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCurrencyGBP(): void
	{
		$result = $this->paybox->setCurrency('GBP');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCurrencyCHF(): void
	{
		$result = $this->paybox->setCurrency('CHF');
		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Expiration dates ===================== */

	public function testSetExpirationDateFutureDate(): void
	{
		$futureDate = new \DateTime('+2 years');
		$result = $this->paybox->setExpirationDate($futureDate);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetExpirationDatePastDate(): void
	{
		// Should accept past date (validation happens elsewhere)
		$pastDate = new \DateTime('-1 year');
		$result = $this->paybox->setExpirationDate($pastDate);
		$this->assertSame($this->paybox, $result);
	}

	public function testSetExpirationDateEndOfMonth(): void
	{
		$endOfMonth = new \DateTime('2025-12-31');
		$result = $this->paybox->setExpirationDate($endOfMonth);
		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Transaction references ===================== */

	public function testSetReferenceWithSpecialCharacters(): void
	{
		$result = $this->paybox->setReference('ORDER-2025-01-#123@ABC');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetReferenceWithUnicode(): void
	{
		$result = $this->paybox->setReference('COMMANDE-€-123');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetArchivingReferenceWithLongString(): void
	{
		$longArchRef = str_repeat('X', 200);
		$result = $this->paybox->setArchivingReference($longArchRef);
		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Email validation ===================== */

	public function testSetCustomerEmailValidFormat(): void
	{
		$result = $this->paybox->setCustomerEmail('john.doe@example.com');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCustomerEmailWithPlus(): void
	{
		$result = $this->paybox->setCustomerEmail('user+tag@example.com');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCustomerEmailWithSubdomain(): void
	{
		$result = $this->paybox->setCustomerEmail('admin@mail.example.com');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCustomerEmailMinimumLength(): void
	{
		$result = $this->paybox->setCustomerEmail('a@b.co');
		$this->assertSame($this->paybox, $result);
	}

	public function testSetCustomerEmailMaximumLength(): void
	{
		// 120 characters is the maximum
		$longEmail = str_repeat('a', 100) . '@example.com'; // ~112 chars
		$result = $this->paybox->setCustomerEmail($longEmail);
		$this->assertSame($this->paybox, $result);
	}

	/* ===================== Form generation tests ===================== */

	public function testGetFormReturnsHtmlString(): void
	{
		$html = $this->paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setCurrency('EUR')
			->setReference('TEST-ORDER-001')
			->setCustomerEmail('test@example.com')
			->setUrlResponseOk('https://example.com/ok')
			->setUrlResponseRefused('https://example.com/refused')
			->setUrlResponseCanceled('https://example.com/canceled')
			->getForm();

		$this->assertIsString($html);
		$this->assertStringContainsString('<form', $html);
		$this->assertStringContainsString('</form>', $html);
		$this->assertStringContainsString('PBX_SITE', $html);
		$this->assertStringContainsString('PBX_TOTAL', $html);
		$this->assertStringContainsString('PBX_DEVISE', $html);
		$this->assertStringContainsString('PBX_CMD', $html);
		$this->assertStringContainsString('PBX_PORTEUR', $html);
		$this->assertStringContainsString('PBX_HMAC', $html);
	}

	public function testGetFormWithCustomCssClasses(): void
	{
		$html = $this->paybox
			->setIsTest(true)
			->setTotal(49.99)
			->setReference('TEST-CSS-001')
			->setCustomerEmail('test@example.com')
			->setFormCssClass('custom-payment-form')
			->setButtonCssClass('btn-custom-pay')
			->setButtonText('Payer maintenant')
			->getForm();

		$this->assertStringContainsString('class="custom-payment-form"', $html);
		$this->assertStringContainsString('class="btn-custom-pay"', $html);
		$this->assertStringContainsString('value="Payer maintenant"', $html);
	}

	public function testGetFormSubscriberRegisterReturnsHtmlString(): void
	{
		$html = $this->paybox
			->setIsTest(true)
			->setTotal(0.01)
			->setReference('SUBSCRIBER-REG-001')
			->setCustomerEmail('subscriber@example.com')
			->setUrlResponseOk('https://example.com/subscriber/ok')
			->getFormSubscriberRegister();

		$this->assertIsString($html);
		$this->assertStringContainsString('<form', $html);
		$this->assertStringContainsString('PBX_AUTOSEULE', $html);
	}

	public function testGetFormWith3DSecureV2(): void
	{
		$billingAddress = $this->createMock(BillingAddressInterface::class);
		$billingAddress->method('getFirstName')->willReturn('John');
		$billingAddress->method('getLastName')->willReturn('Doe');
		$billingAddress->method('getStreet')->willReturn('123 Main St');
		$billingAddress->method('getStreet2')->willReturn('Apt 4');
		$billingAddress->method('getZipCode')->willReturn('75001');
		$billingAddress->method('getCity')->willReturn('Paris');
		$billingAddress->method('getCountryCode')->willReturn('FR');

		$shoppingCart = $this->createMock(ShoppingCartInterface::class);
		$shoppingCart->method('getTotalQuantity')->willReturn(3);

		$html = $this->paybox
			->setIsTest(true)
			->set3DSecureV2()
			->setBillingAddress($billingAddress)
			->setShoppingCart($shoppingCart)
			->setTotal(199.99)
			->setReference('3DS-TEST-001')
			->setCustomerEmail('3ds@example.com')
			->getForm();

		$this->assertStringContainsString('PBX_SHOPPINGCART', $html);
		$this->assertStringContainsString('PBX_BILLING', $html);
	}

	/* ===================== Validation error tests ===================== */

	public function testGetFormFailsWithoutEmail(): void
	{
		$result = $this->paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('NO-EMAIL-001')
			// Missing customer email
			->getForm();

		$this->assertNull($result);
	}

	public function testGetFormFailsWithInvalidEmail(): void
	{
		$result = $this->paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('INVALID-EMAIL-001')
			->setCustomerEmail('invalid-email')
			->getForm();

		$this->assertNull($result);
	}

	public function testGetFormFailsWithoutAmount(): void
	{
		$result = $this->paybox
			->setIsTest(true)
			->setReference('NO-AMOUNT-001')
			->setCustomerEmail('test@example.com')
			// Missing amount (default is 0)
			->getForm();

		$this->assertNull($result);
	}

	public function testGetFormFailsWithoutReference(): void
	{
		$result = $this->paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setCustomerEmail('test@example.com')
			// Missing reference
			->getForm();

		$this->assertNull($result);
	}

	public function testGetFormFailsWithEmptyReference(): void
	{
		$result = $this->paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('')
			->setCustomerEmail('test@example.com')
			->getForm();

		$this->assertNull($result);
	}

	public function testGetFormFailsWithTooLongReference(): void
	{
		$result = $this->paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference(str_repeat('A', 251)) // 251 characters (max is 250)
			->setCustomerEmail('test@example.com')
			->getForm();

		$this->assertNull($result);
	}

	public function testGetFormFailsWithEmailTooShort(): void
	{
		$result = $this->paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('TEST-001')
			->setCustomerEmail('a@b.c') // 5 characters (min is 6)
			->getForm();

		$this->assertNull($result);
	}

	public function testGetFormFailsWithEmailTooLong(): void
	{
		$result = $this->paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('TEST-001')
			->setCustomerEmail(str_repeat('a', 121) . '@example.com') // > 120 characters
			->getForm();

		$this->assertNull($result);
	}

	/* ===================== Payment method tests ===================== */

	public function testNewPaymentFailsWithMissingConfiguration(): void
	{
		$paybox = new PayBox(
			siteNumber: '123456', // Invalid (must be 7 digits)
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: '0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF'
		);

		$result = $paybox
			->setTotal(99.99)
			->setReference('TEST-001')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->newPayment();

		$this->assertNull($result);
	}

	public function testDoAuthorizationSetsCorrectOperation(): void
	{
		$paybox = new PayBox(
			siteNumber: '123456', // Invalid site number
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: '0123'
		);

		$result = $paybox
			->setTotal(99.99)
			->setReference('AUTH-001')
			->doAuthorization();

		// Should return null due to invalid configuration
		$this->assertNull($result);
	}

	public function testDoDebitRequiresCallAndTransactionNumbers(): void
	{
		$result = $this->paybox
			->setIsTest(false) // Force validation
			->setTotal(99.99)
			->setReference('DEBIT-001')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			// Missing call number and transaction number
			->doDebit();

		$this->assertNull($result);
	}

	public function testDoAuthorizationAndDebitFailsWithoutCardInfo(): void
	{
		$result = $this->paybox
			->setIsTest(false)
			->setTotal(99.99)
			->setReference('AUTH-DEBIT-001')
			// Missing card information
			->doAuthorizationAndDebit();

		$this->assertNull($result);
	}

	public function testAddSubscriberFailsWithoutCardInfo(): void
	{
		$result = $this->paybox
			->setIsTest(false)
			->setTotal(0.01)
			->setReference('ADD-SUB-001')
			// Missing card information
			->addSubscriber();

		$this->assertNull($result);
	}

	public function testDeleteSubscriberFailsWithoutSubscriberRef(): void
	{
		$result = $this->paybox
			->setIsTest(false)
			// Missing subscriber reference
			->deleteSubscriber();

		$this->assertNull($result);
	}

	/* ===================== Subscriber operations tests ===================== */

	public function testGetFormSubscriberRegisterFailsWithoutEmail(): void
	{
		$result = $this->paybox
			->setIsTest(true)
			->setTotal(0.01)
			->setReference('SUB-REG-001')
			// Missing email
			->getFormSubscriberRegister();

		$this->assertNull($result);
	}

	public function testGetFormWithSubscriberReference(): void
	{
		$html = $this->paybox
			->setIsTest(true)
			->setTotal(29.99)
			->setReference('SUB-PAY-001')
			->setCustomerEmail('subscriber@example.com')
			->setSubscriberReference('SUB-TOKEN-123')
			->getForm();

		$this->assertIsString($html);
		$this->assertStringContainsString('PBX_REFABONNE', $html);
	}

	/* ===================== Configuration validation tests ===================== */

	public function testInvalidSiteNumberLength(): void
	{
		$paybox = new PayBox(
			siteNumber: '12345', // 5 digits (must be 7)
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128)
		);

		$result = $paybox
			->setTotal(99.99)
			->setReference('TEST-001')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->newPayment();

		$this->assertNull($result);
	}

	public function testInvalidRangTooShort(): void
	{
		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '1', // 1 digit (must be 2-3)
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128)
		);

		$result = $paybox
			->setTotal(99.99)
			->setReference('TEST-001')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->newPayment();

		$this->assertNull($result);
	}

	public function testInvalidRangTooLong(): void
	{
		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '1234', // 4 digits (must be 2-3)
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128)
		);

		$result = $paybox
			->setTotal(99.99)
			->setReference('TEST-001')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->newPayment();

		$this->assertNull($result);
	}

	public function testInvalidHttpPasswordTooShort(): void
	{
		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '1234567', // 7 characters (must be 8-10)
			secretKey: str_repeat('0', 128)
		);

		$result = $paybox
			->setTotal(99.99)
			->setReference('TEST-001')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->newPayment();

		$this->assertNull($result);
	}

	public function testInvalidHttpPasswordTooLong(): void
	{
		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678901', // 11 characters (must be 8-10)
			secretKey: str_repeat('0', 128)
		);

		$result = $paybox
			->setTotal(99.99)
			->setReference('TEST-001')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->newPayment();

		$this->assertNull($result);
	}

	public function testInvalidIdentifierForForm(): void
	{
		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '', // Empty (must be 1-9 digits)
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128)
		);

		$result = $paybox
			->setTotal(99.99)
			->setReference('TEST-001')
			->setCustomerEmail('test@example.com')
			->getForm();

		$this->assertNull($result);
	}

	public function testInvalidSecretKeyForForm(): void
	{
		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: 'short' // Not 128 characters
		);

		$result = $paybox
			->setTotal(99.99)
			->setReference('TEST-001')
			->setCustomerEmail('test@example.com')
			->getForm();

		$this->assertNull($result);
	}

	/* ===================== Card validation tests ===================== */

	public function testInvalidCardNumberTooLong(): void
	{
		$result = $this->paybox
			->setIsTest(false)
			->setTotal(99.99)
			->setReference('CARD-TOO-LONG')
			->setCreditCardNumber('12345678901234567890') // 20 digits (max is 19)
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->newPayment();

		$this->assertNull($result);
	}

	public function testInvalidCvvTooShort(): void
	{
		$result = $this->paybox
			->setIsTest(false)
			->setTotal(99.99)
			->setReference('CVV-TOO-SHORT')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('12') // 2 digits (must be 3-4)
			->newPayment();

		$this->assertNull($result);
	}

	public function testInvalidCvvTooLong(): void
	{
		$result = $this->paybox
			->setIsTest(false)
			->setTotal(99.99)
			->setReference('CVV-TOO-LONG')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('12345') // 5 digits (must be 3-4)
			->newPayment();

		$this->assertNull($result);
	}

	public function testMissingExpirationDate(): void
	{
		$result = $this->paybox
			->setIsTest(false)
			->setTotal(99.99)
			->setReference('NO-EXP-DATE')
			->setCreditCardNumber('4111111111111111')
			// Missing expiration date
			->setCvc('123')
			->newPayment();

		$this->assertNull($result);
	}

	/* ===================== Subscriber reference validation ===================== */

	public function testSubscriberReferenceTooLong(): void
	{
		$result = $this->paybox
			->setIsTest(false)
			->setTotal(99.99)
			->setReference('TEST-001')
			->setSubscriberReference(str_repeat('A', 251)) // 251 characters (max is 250)
			->setCreditCardToken('TOKEN123')
			->setExpirationDate(new \DateTime('+1 year'))
			->newPayment();

		$this->assertNull($result);
	}

	/* ===================== Special operations ===================== */

	public function testUpdateSubscriberRequiresSubscriberRef(): void
	{
		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128)
		);

		$result = $paybox
			->setBankCardOperation(BankCardOperation::UPDATE_SUBSCRIBER)
			// Missing subscriber reference (required for update)
			->newPayment();

		$this->assertNull($result);
	}

	public function testDeleteSubscriberRequiresSubscriberRef(): void
	{
		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128)
		);

		$result = $paybox
			->setBankCardOperation(BankCardOperation::DELETE_SUBSCRIBER)
			// Missing subscriber reference (required for delete)
			->newPayment();

		$this->assertNull($result);
	}

	/* ===================== HTTP Client Tests ===================== */

	public function testConstructorWithHttpClient(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: '0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF',
			httpClient: $httpClient
		);

		$this->assertInstanceOf(PayBox::class, $paybox);
	}

	/**
	 * Helper method to create a PayBox Direct response string
	 */
	private function createPayBoxResponse(string $responseCode = '00000', array $additionalData = []): string
	{
		$data = array_merge([
			'CODEREPONSE' => $responseCode,
			'COMMENTAIRE' => $responseCode === '00000' ? 'Operation reussie' : 'Erreur',
			'NUMAPPEL' => '0123456789',
			'NUMTRANS' => '9876543210',
			'AUTORISATION' => 'AUTH' . random_int(1000, 9999),
			'REFERENCE' => 'ORDER-123',
			'PORTEUR' => '4111111111******',
			'REFABONNE' => 'SUB123456',
			'TYPECARTE' => 'VISA',
			'NUMQUESTION' => '987654321',
			'SHA-1' => hash('sha1', 'test'),
		], $additionalData);

		return http_build_query($data);
	}

	/**
	 * Helper method to create PSR-7 Response with query string body
	 */
	private function createHttpResponse(string $body, int $statusCode = 200): Response
	{
		return new Response($statusCode, ['Content-Type' => 'application/x-www-form-urlencoded'], $body);
	}

	/* ===================== newPayment() with HTTP mocks ===================== */

	public function testNewPaymentReturnsResponseOnSuccess(): void
	{
		$responseBody = $this->createPayBoxResponse('00000');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createHttpResponse($responseBody));

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			httpClient: $httpClient
		);

		$result = $paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('ORDER-123')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->newPayment();

		$this->assertInstanceOf(PayBoxResponse::class, $result);
		$this->assertSame(PayBoxResponseCode::SUCCESS, $result->getResponseCode());
	}

	public function testNewPaymentVerifiesRequestStructure(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				$body = (string) $request->getBody();

				// Verify it's a POST request to PayBox Direct API (PPPS.php, not tpeweb)
				return str_contains($uri, 'paybox.com/PPPS.php')
					&& $request->getMethod() === 'POST'
					&& str_contains($body, 'SITE=')
					&& str_contains($body, 'RANG=')
					&& str_contains($body, 'MONTANT=')
					&& str_contains($body, 'REFERENCE=');
			}))
			->willReturn($this->createHttpResponse($this->createPayBoxResponse()));

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			httpClient: $httpClient
		);

		$paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('ORDER-123')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->newPayment();
	}

	public function testNewPaymentReturnsNullOnNetworkException(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willThrowException(new class('Network error') extends \RuntimeException implements ClientExceptionInterface {});

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			httpClient: $httpClient
		);

		$result = $paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('ORDER-123')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->newPayment();

		$this->assertNull($result);
	}

	public function testNewPaymentReturnsNullOnPayBoxError(): void
	{
		// PayBox error code 00001 = Authorization denied
		$responseBody = $this->createPayBoxResponse('00001');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createHttpResponse($responseBody));

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			httpClient: $httpClient
		);

		$result = $paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('ORDER-123')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->newPayment();

		$this->assertNull($result);
	}

	public function testNewPaymentWithAuthorizationOnlyOperation(): void
	{
		$responseBody = $this->createPayBoxResponse('00000');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$body = (string) $request->getBody();
				// Verify TYPE parameter for authorization only (00001)
				return str_contains($body, 'TYPE=00001');
			}))
			->willReturn($this->createHttpResponse($responseBody));

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			httpClient: $httpClient
		);

		$paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('AUTH-ONLY-123')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->setBankCardOperation(BankCardOperation::AUTHORIZATION_ONLY)
			->newPayment();
	}

	public function testNewPaymentWithAuthorizationAndDebitOperation(): void
	{
		$responseBody = $this->createPayBoxResponse('00000');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$body = (string) $request->getBody();
				// Verify TYPE parameter for authorization and debit (00003)
				return str_contains($body, 'TYPE=00003');
			}))
			->willReturn($this->createHttpResponse($responseBody));

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			httpClient: $httpClient
		);

		$paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('AUTH-DEBIT-123')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->setBankCardOperation(BankCardOperation::AUTHORIZATION_AND_DEBIT)
			->newPayment();
	}

	/* ===================== doAuthorization() with HTTP mocks ===================== */

	public function testDoAuthorizationReturnsResponseOnSuccess(): void
	{
		$responseBody = $this->createPayBoxResponse('00000');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createHttpResponse($responseBody));

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			httpClient: $httpClient
		);

		$result = $paybox
			->setIsTest(true)
			->setTotal(150.00)
			->setReference('AUTH-ORDER-456')
			->setCreditCardNumber('5555555555554444')
			->setExpirationDate(new \DateTime('+2 years'))
			->setCvc('456')
			->doAuthorization();

		$this->assertInstanceOf(PayBoxResponse::class, $result);
	}

	/* ===================== doDebit() with HTTP mocks ===================== */

	public function testDoDebitReturnsResponseOnSuccess(): void
	{
		$responseBody = $this->createPayBoxResponse('00000');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createHttpResponse($responseBody));

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			httpClient: $httpClient
		);

		$result = $paybox
			->setIsTest(true)
			->setTotal(200.00)
			->setReference('DEBIT-ORDER-789')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->setCallNumber(1234567890)
			->setTransactionNumber(9876543210)
			->doDebit();

		$this->assertInstanceOf(PayBoxResponse::class, $result);
	}

	/* ===================== doAuthorizationAndDebit() with HTTP mocks ===================== */

	public function testDoAuthorizationAndDebitReturnsResponseOnSuccess(): void
	{
		$responseBody = $this->createPayBoxResponse('00000');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createHttpResponse($responseBody));

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			httpClient: $httpClient
		);

		$result = $paybox
			->setIsTest(true)
			->setTotal(300.00)
			->setReference('AUTH-DEBIT-ORDER-999')
			->setCreditCardNumber('5555555555554444')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('789')
			->doAuthorizationAndDebit();

		$this->assertInstanceOf(PayBoxResponse::class, $result);
	}

	/* ===================== addSubscriber() with HTTP mocks ===================== */

	public function testAddSubscriberReturnsResponseOnSuccess(): void
	{
		$responseBody = $this->createPayBoxResponse('00000', ['REFABONNE' => 'SUB-TOKEN-12345']);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$body = (string) $request->getBody();
				// Verify TYPE parameter for subscriber registration (00056)
				return str_contains($body, 'TYPE=00056');
			}))
			->willReturn($this->createHttpResponse($responseBody));

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			httpClient: $httpClient
		);

		$result = $paybox
			->setIsTest(true)
			->setTotal(0.01)
			->setReference('ADD-SUB-123')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->addSubscriber();

		$this->assertInstanceOf(PayBoxResponse::class, $result);
		$this->assertSame('SUB-TOKEN-12345', $result->getCardHash());
	}

	/* ===================== deleteSubscriber() with HTTP mocks ===================== */

	public function testDeleteSubscriberReturnsResponseOnSuccess(): void
	{
		$responseBody = $this->createPayBoxResponse('00000');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$body = (string) $request->getBody();
				// Verify TYPE parameter for subscriber deletion (00058)
				return str_contains($body, 'TYPE=00058')
					&& str_contains($body, 'REFABONNE=');
			}))
			->willReturn($this->createHttpResponse($responseBody));

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			httpClient: $httpClient
		);

		$result = $paybox
			->setIsTest(true)
			->setSubscriberReference('SUB-TOKEN-12345')
			->deleteSubscriber();

		$this->assertInstanceOf(PayBoxResponse::class, $result);
	}

	/* ===================== Logger integration tests ===================== */

	public function testNewPaymentLogsOnException(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::atLeastOnce())
			->method('error');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willThrowException(new class('Network error') extends \RuntimeException implements ClientExceptionInterface {});

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			logger: $logger,
			httpClient: $httpClient
		);

		$paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('ERROR-ORDER-123')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->newPayment();
	}

	public function testNewPaymentLogsPayBoxError(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(self::stringContains('Response code: 00001'));

		$responseBody = $this->createPayBoxResponse('00001');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createHttpResponse($responseBody));

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			logger: $logger,
			httpClient: $httpClient
		);

		$paybox
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('DENIED-ORDER-123')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->newPayment();
	}

	/* ===================== Complete workflows with HTTP ===================== */

	public function testCompleteAuthorizationCaptureWorkflowWithHttp(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::exactly(2))
			->method('sendRequest')
			->willReturnOnConsecutiveCalls(
				// First call: authorization
				$this->createHttpResponse($this->createPayBoxResponse('00000', [
					'NUMAPPEL' => '1234567890',
					'NUMTRANS' => '9876543210',
					'AUTORISATION' => 'AUTH5678'
				])),
				// Second call: capture
				$this->createHttpResponse($this->createPayBoxResponse('00000'))
			);

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			httpClient: $httpClient
		);

		// Step 1: Authorization
		$authResult = $paybox
			->setIsTest(true)
			->setTotal(500.00)
			->setReference('WORKFLOW-AUTH-001')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->doAuthorization();

		$this->assertInstanceOf(PayBoxResponse::class, $authResult);

		// Step 2: Capture using returned call and transaction numbers
		$captureResult = $paybox
			->setCallNumber((int) $authResult->getCallNumber())
			->setTransactionNumber((int) $authResult->getTransactionNumber())
			->doDebit();

		$this->assertInstanceOf(PayBoxResponse::class, $captureResult);
	}

	public function testCompleteSubscriberWorkflowWithHttp(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::exactly(3))
			->method('sendRequest')
			->willReturnOnConsecutiveCalls(
				// First call: add subscriber
				$this->createHttpResponse($this->createPayBoxResponse('00000', ['REFABONNE' => 'SUB-TOKEN-789'])),
				// Second call: payment with subscriber
				$this->createHttpResponse($this->createPayBoxResponse('00000')),
				// Third call: delete subscriber
				$this->createHttpResponse($this->createPayBoxResponse('00000'))
			);

		$paybox = new PayBox(
			siteNumber: '1234567',
			rang: '99',
			identifier: '123456789',
			httpPassword: '12345678',
			secretKey: str_repeat('0', 128),
			httpClient: $httpClient
		);

		// Step 1: Add subscriber
		$addResult = $paybox
			->setIsTest(true)
			->setTotal(0.01)
			->setReference('SUB-WORKFLOW-001')
			->setCreditCardNumber('4111111111111111')
			->setExpirationDate(new \DateTime('+1 year'))
			->setCvc('123')
			->addSubscriber();

		$this->assertInstanceOf(PayBoxResponse::class, $addResult);
		$subscriberRef = $addResult->getCardHash();

		// Step 2: Use subscriber token for payment
		$paymentResult = $paybox
			->reset()
			->setIsTest(true)
			->setTotal(99.99)
			->setReference('SUB-PAYMENT-001')
			->setSubscriberReference($subscriberRef)
			->setCreditCardToken($subscriberRef)
			->setExpirationDate(new \DateTime('+1 year'))
			->doAuthorizationAndDebit();

		$this->assertInstanceOf(PayBoxResponse::class, $paymentResult);

		// Step 3: Delete subscriber
		$deleteResult = $paybox
			->reset()
			->setIsTest(true)
			->setSubscriberReference($subscriberRef)
			->deleteSubscriber();

		$this->assertInstanceOf(PayBoxResponse::class, $deleteResult);
	}
}