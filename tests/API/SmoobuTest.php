<?php

namespace Tests\API;

use Osimatic\API\Smoobu;
use Osimatic\Network\HTTPClient;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class SmoobuTest extends TestCase
{
	private Smoobu $smoobu;
	private LoggerInterface $logger;

	protected function setUp(): void
	{
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->smoobu = new Smoobu('test-api-key', 'test-api-secret', $this->logger);
	}

	// ========================================
	// Constructor & Configuration Tests
	// ========================================

	public function testConstructor(): void
	{
		$smoobu = new Smoobu();

		self::assertInstanceOf(Smoobu::class, $smoobu);
	}

	public function testConstructorWithApiKey(): void
	{
		$smoobu = new Smoobu('test-api-key');

		self::assertInstanceOf(Smoobu::class, $smoobu);
	}

	public function testConstructorWithApiSecret(): void
	{
		$smoobu = new Smoobu('test-api-key', 'test-api-secret');

		self::assertInstanceOf(Smoobu::class, $smoobu);
	}

	public function testConstructorWithCustomLogger(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$smoobu = new Smoobu('test-api-key', 'test-api-secret', $logger);

		self::assertInstanceOf(Smoobu::class, $smoobu);
	}

	public function testConstructorWithCustomHttpClient(): void
	{
		$httpClient = $this->createMock(HTTPClient::class);
		$smoobu = new Smoobu('test-api-key', 'test-api-secret', $this->logger, $httpClient);

		self::assertInstanceOf(Smoobu::class, $smoobu);
	}

	public function testSetApiKey(): void
	{
		$smoobu = new Smoobu();
		$result = $smoobu->setApiKey('new-api-key');

		self::assertSame($smoobu, $result);
	}

	public function testSetApiKeyMethodChaining(): void
	{
		$smoobu = new Smoobu();
		$result = $smoobu->setApiKey('key1')->setApiKey('key2');

		self::assertSame($smoobu, $result);
	}

	public function testSetApiSecret(): void
	{
		$smoobu = new Smoobu();
		$result = $smoobu->setApiSecret('new-api-secret');

		self::assertSame($smoobu, $result);
	}

	public function testSetApiSecretMethodChaining(): void
	{
		$smoobu = new Smoobu();
		$result = $smoobu->setApiSecret('secret1')->setApiSecret('secret2');

		self::assertSame($smoobu, $result);
	}

	public function testApiUrlConstant(): void
	{
		self::assertSame('https://login.smoobu.com', Smoobu::API_URL);
	}

	public function testRateLimitConstant(): void
	{
		self::assertSame(1000, Smoobu::RATE_LIMIT_PER_MINUTE);
	}

	// ========================================
	// User Methods Tests
	// ========================================

	public function testGetMe(): void
	{
		// Note: This test requires mocking HTTPRequestExecutor
		// In a real scenario, we would mock the HTTP response
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getMe();

		self::assertNull($result);
	}

	// ========================================
	// Apartments/Properties Methods Tests
	// ========================================

	public function testGetApartments(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getApartments();

		self::assertNull($result);
	}

	public function testGetApartment(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getApartment(123);

		self::assertNull($result);
	}

	public function testGetApartmentWithInvalidId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid apartment ID. Must be a positive integer.');

		$result = $this->smoobu->getApartment(0);

		self::assertNull($result);
	}

	public function testGetApartmentWithNegativeId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid apartment ID. Must be a positive integer.');

		$result = $this->smoobu->getApartment(-5);

		self::assertNull($result);
	}

	// ========================================
	// Reservations Methods Tests
	// ========================================

	public function testGetReservations(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getReservations();

		self::assertNull($result);
	}

	public function testGetReservationsWithFilters(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$filters = [
			'from' => '2024-01-01',
			'to' => '2024-12-31',
			'apartmentId' => 123,
		];
		$result = $smoobu->getReservations($filters);

		self::assertNull($result);
	}

	public function testGetReservation(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getReservation(456);

		self::assertNull($result);
	}

	public function testGetReservationWithInvalidId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid reservation ID. Must be a positive integer.');

		$result = $this->smoobu->getReservation(0);

		self::assertNull($result);
	}

	public function testCreateReservation(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$reservationData = [
			'arrivalDate' => '2024-06-01',
			'departureDate' => '2024-06-07',
			'apartmentId' => 123,
		];
		$result = $smoobu->createReservation($reservationData);

		self::assertNull($result);
	}

	public function testCreateReservationWithMissingRequiredFields(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Missing required fields: arrivalDate, departureDate, and apartmentId are required.');

		$result = $this->smoobu->createReservation(['arrivalDate' => '2024-06-01']);

		self::assertNull($result);
	}

	public function testCreateReservationWithAllFields(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$reservationData = [
			'arrivalDate' => '2024-06-01',
			'departureDate' => '2024-06-07',
			'apartmentId' => 123,
			'firstname' => 'John',
			'lastname' => 'Doe',
			'email' => 'john@example.com',
			'phone' => '+1234567890',
			'adults' => 2,
			'children' => 1,
			'price' => 500.00,
			'prepayment' => 100.00,
			'deposit' => 50.00,
			'notice' => 'Internal note',
			'guestNotice' => 'Guest visible note',
			'channel' => 'booking.com',
			'reference' => 'EXT-123',
		];
		$result = $smoobu->createReservation($reservationData);

		self::assertNull($result);
	}

	public function testUpdateReservation(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$reservationData = [
			'arrivalDate' => '2024-06-01',
			'departureDate' => '2024-06-08',
		];
		$result = $smoobu->updateReservation(456, $reservationData);

		self::assertNull($result);
	}

	public function testUpdateReservationWithInvalidId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid reservation ID. Must be a positive integer.');

		$result = $this->smoobu->updateReservation(-1, ['arrivalDate' => '2024-06-01']);

		self::assertNull($result);
	}

	public function testCancelReservation(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->cancelReservation(456);

		self::assertFalse($result);
	}

	public function testCancelReservationWithInvalidId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid reservation ID. Must be a positive integer.');

		$result = $this->smoobu->cancelReservation(0);

		self::assertFalse($result);
	}

	public function testDeleteReservation(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid reservation ID. Must be a positive integer.');

		$result = $this->smoobu->deleteReservation(0);

		self::assertFalse($result);
	}

	public function testDeleteReservationIsAliasForCancel(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->deleteReservation(456);

		self::assertFalse($result);
	}

	// ========================================
	// Availability & Rates Methods Tests
	// ========================================

	public function testCheckAvailability(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$checkData = [
			'arrivalDate' => '2024-06-01',
			'departureDate' => '2024-06-07',
		];
		$result = $smoobu->checkAvailability($checkData);

		self::assertNull($result);
	}

	public function testCheckAvailabilityWithMissingRequiredFields(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Missing required fields: arrivalDate and departureDate are required.');

		$result = $this->smoobu->checkAvailability(['arrivalDate' => '2024-06-01']);

		self::assertNull($result);
	}

	public function testCheckAvailabilityWithAllFields(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$checkData = [
			'arrivalDate' => '2024-06-01',
			'departureDate' => '2024-06-07',
			'apartmentId' => 123,
			'adults' => 2,
			'children' => 1,
		];
		$result = $smoobu->checkAvailability($checkData);

		self::assertNull($result);
	}

	public function testGetRates(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getRates();

		self::assertNull($result);
	}

	public function testGetRatesWithFilters(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$filters = [
			'apartmentId' => 123,
			'from' => '2024-01-01',
			'to' => '2024-12-31',
		];
		$result = $smoobu->getRates($filters);

		self::assertNull($result);
	}

	public function testCreateRate(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$rateData = [
			'apartmentId' => 123,
			'startDate' => '2024-06-01',
			'endDate' => '2024-06-30',
			'price' => 100.00,
		];
		$result = $smoobu->createRate($rateData);

		self::assertNull($result);
	}

	public function testCreateRateWithMissingRequiredFields(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Missing required fields: apartmentId, startDate, endDate, and price are required.');

		$result = $this->smoobu->createRate(['apartmentId' => 123]);

		self::assertNull($result);
	}

	public function testCreateRateWithOptionalFields(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$rateData = [
			'apartmentId' => 123,
			'startDate' => '2024-06-01',
			'endDate' => '2024-06-30',
			'price' => 100.00,
			'minimumStay' => 3,
			'available' => true,
		];
		$result = $smoobu->createRate($rateData);

		self::assertNull($result);
	}

	public function testUpdateRate(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Missing required fields: apartmentId, startDate, endDate, and price are required.');

		$result = $this->smoobu->updateRate(['apartmentId' => 123]);

		self::assertNull($result);
	}

	// ========================================
	// Price Elements Methods Tests
	// ========================================

	public function testGetPriceElements(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getPriceElements(456);

		self::assertNull($result);
	}

	public function testGetPriceElementsWithInvalidId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid reservation ID. Must be a positive integer.');

		$result = $this->smoobu->getPriceElements(0);

		self::assertNull($result);
	}

	public function testAddPriceElement(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$priceElementData = [
			'type' => 'cleaning',
			'name' => 'Cleaning fee',
			'price' => 50.00,
		];
		$result = $smoobu->addPriceElement(456, $priceElementData);

		self::assertNull($result);
	}

	public function testAddPriceElementWithInvalidReservationId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid reservation ID. Must be a positive integer.');

		$priceElementData = [
			'type' => 'cleaning',
			'name' => 'Cleaning fee',
			'price' => 50.00,
		];
		$result = $this->smoobu->addPriceElement(-1, $priceElementData);

		self::assertNull($result);
	}

	public function testAddPriceElementWithMissingRequiredFields(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Missing required fields: type, name, and price are required.');

		$result = $this->smoobu->addPriceElement(456, ['type' => 'cleaning']);

		self::assertNull($result);
	}

	public function testAddPriceElementWithOptionalFields(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$priceElementData = [
			'type' => 'cleaning',
			'name' => 'Cleaning fee',
			'price' => 50.00,
			'vatPercent' => 20.0,
			'isPaid' => false,
		];
		$result = $smoobu->addPriceElement(456, $priceElementData);

		self::assertNull($result);
	}

	public function testUpdatePriceElement(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$priceElementData = [
			'type' => 'cleaning',
			'name' => 'Updated cleaning fee',
			'price' => 60.00,
		];
		$result = $smoobu->updatePriceElement(456, 789, $priceElementData);

		self::assertNull($result);
	}

	public function testUpdatePriceElementWithInvalidIds(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid reservation ID or price element ID. Must be positive integers.');

		$result = $this->smoobu->updatePriceElement(0, 789, ['price' => 60.00]);

		self::assertNull($result);
	}

	public function testUpdatePriceElementWithInvalidPriceElementId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid reservation ID or price element ID. Must be positive integers.');

		$result = $this->smoobu->updatePriceElement(456, 0, ['price' => 60.00]);

		self::assertNull($result);
	}

	public function testDeletePriceElement(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->deletePriceElement(456, 789);

		self::assertFalse($result);
	}

	public function testDeletePriceElementWithInvalidIds(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid reservation ID or price element ID. Must be positive integers.');

		$result = $this->smoobu->deletePriceElement(-1, 789);

		self::assertFalse($result);
	}

	// ========================================
	// Guests Methods Tests
	// ========================================

	public function testGetGuests(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getGuests();

		self::assertNull($result);
	}

	public function testGetGuestsWithFilters(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$filters = [
			'page' => 1,
			'pageSize' => 50,
		];
		$result = $smoobu->getGuests($filters);

		self::assertNull($result);
	}

	public function testGetGuest(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getGuest(123);

		self::assertNull($result);
	}

	public function testGetGuestWithInvalidId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid guest ID. Must be a positive integer.');

		$result = $this->smoobu->getGuest(0);

		self::assertNull($result);
	}

	// ========================================
	// Messages Methods Tests
	// ========================================

	public function testGetMessages(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getMessages(456);

		self::assertNull($result);
	}

	public function testGetMessagesWithInvalidId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid reservation ID. Must be a positive integer.');

		$result = $this->smoobu->getMessages(-5);

		self::assertNull($result);
	}

	public function testGetMessageThreads(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getMessageThreads();

		self::assertNull($result);
	}

	public function testSendMessageToGuest(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->sendMessageToGuest(456, 'Hello guest!');

		self::assertNull($result);
	}

	public function testSendMessageToGuestWithInvalidId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid reservation ID. Must be a positive integer.');

		$result = $this->smoobu->sendMessageToGuest(0, 'Hello guest!');

		self::assertNull($result);
	}

	public function testSendMessageToGuestWithEmptyMessage(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Message content cannot be empty.');

		$result = $this->smoobu->sendMessageToGuest(456, '');

		self::assertNull($result);
	}

	public function testSendMessageToGuestWithOptions(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$options = [
			'subject' => 'Welcome',
			'sendCopy' => true,
		];
		$result = $smoobu->sendMessageToGuest(456, 'Hello guest!', $options);

		self::assertNull($result);
	}

	public function testSendMessageToHost(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->sendMessageToHost(456, 'Internal note');

		self::assertNull($result);
	}

	public function testSendMessageToHostWithInvalidId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid reservation ID. Must be a positive integer.');

		$result = $this->smoobu->sendMessageToHost(-1, 'Internal note');

		self::assertNull($result);
	}

	public function testSendMessageToHostWithEmptyMessage(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Message content cannot be empty.');

		$result = $this->smoobu->sendMessageToHost(456, '');

		self::assertNull($result);
	}

	public function testSendMessageToHostWithOptions(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$options = ['subject' => 'Important note'];
		$result = $smoobu->sendMessageToHost(456, 'Internal note', $options);

		self::assertNull($result);
	}

	// ========================================
	// Addons Methods Tests
	// ========================================

	public function testGetAddons(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getAddons();

		self::assertNull($result);
	}

	public function testGetApartmentAddons(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getApartmentAddons(123);

		self::assertNull($result);
	}

	public function testGetApartmentAddonsWithInvalidId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid apartment ID. Must be a positive integer.');

		$result = $this->smoobu->getApartmentAddons(0);

		self::assertNull($result);
	}

	// ========================================
	// Placeholders Methods Tests
	// ========================================

	public function testGetReservationPlaceholders(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getReservationPlaceholders(456);

		self::assertNull($result);
	}

	public function testGetReservationPlaceholdersWithInvalidId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid reservation ID. Must be a positive integer.');

		$result = $this->smoobu->getReservationPlaceholders(-1);

		self::assertNull($result);
	}

	public function testGetCustomPlaceholders(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getCustomPlaceholders();

		self::assertNull($result);
	}

	public function testCreateCustomPlaceholder(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$placeholderData = [
			'name' => 'custom_field',
			'value' => 'Default value',
		];
		$result = $smoobu->createCustomPlaceholder($placeholderData);

		self::assertNull($result);
	}

	public function testCreateCustomPlaceholderWithMissingRequiredFields(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Missing required fields: name and value are required.');

		$result = $this->smoobu->createCustomPlaceholder(['name' => 'custom_field']);

		self::assertNull($result);
	}

	public function testCreateCustomPlaceholderWithTranslations(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$placeholderData = [
			'name' => 'custom_field',
			'value' => 'Default value',
			'translations' => [
				'en' => 'English value',
				'de' => 'German value',
				'fr' => 'French value',
			],
		];
		$result = $smoobu->createCustomPlaceholder($placeholderData);

		self::assertNull($result);
	}

	public function testUpdateCustomPlaceholder(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$placeholderData = [
			'name' => 'custom_field',
			'value' => 'Updated value',
		];
		$result = $smoobu->updateCustomPlaceholder(789, $placeholderData);

		self::assertNull($result);
	}

	public function testUpdateCustomPlaceholderWithInvalidId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid placeholder ID. Must be a positive integer.');

		$result = $this->smoobu->updateCustomPlaceholder(0, ['value' => 'Updated']);

		self::assertNull($result);
	}

	public function testDeleteCustomPlaceholder(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->deleteCustomPlaceholder(789);

		self::assertFalse($result);
	}

	public function testDeleteCustomPlaceholderWithInvalidId(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid placeholder ID. Must be a positive integer.');

		$result = $this->smoobu->deleteCustomPlaceholder(-5);

		self::assertFalse($result);
	}

	// ========================================
	// Online Check-In Methods Tests
	// ========================================

	public function testGetOnlineCheckIn(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getOnlineCheckIn();

		self::assertNull($result);
	}

	public function testGetOnlineCheckInWithFilters(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$filters = ['reservationId' => 456];
		$result = $smoobu->getOnlineCheckIn($filters);

		self::assertNull($result);
	}

	public function testCreateOnlineCheckIn(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$checkInData = ['reservationId' => 456];
		$result = $smoobu->createOnlineCheckIn($checkInData);

		self::assertNull($result);
	}

	public function testCreateOnlineCheckInWithMissingRequiredFields(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Missing required field: reservationId is required.');

		$result = $this->smoobu->createOnlineCheckIn([]);

		self::assertNull($result);
	}

	public function testCreateOnlineCheckInWithOptions(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$checkInData = [
			'reservationId' => 456,
			'sendEmail' => true,
		];
		$result = $smoobu->createOnlineCheckIn($checkInData);

		self::assertNull($result);
	}

	// ========================================
	// Edge Cases Tests
	// ========================================

	public function testMultipleMethodCallsWithSameInstance(): void
	{
		$this->logger->expects(self::exactly(3))
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);

		$result1 = $smoobu->getApartments();
		$result2 = $smoobu->getReservations();
		$result3 = $smoobu->getGuests();

		self::assertNull($result1);
		self::assertNull($result2);
		self::assertNull($result3);
	}

	public function testApiKeyCanBeChangedAfterInstantiation(): void
	{
		$smoobu = new Smoobu('initial-key', 'initial-secret');
		$smoobu->setApiKey('new-key');

		self::assertInstanceOf(Smoobu::class, $smoobu);
	}

	public function testApiKeySetButApiSecretMissing(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu('test-api-key', null, $this->logger);
		$result = $smoobu->getApartments();

		self::assertNull($result);
	}

	public function testApiSecretSetButApiKeyMissing(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, 'test-api-secret', $this->logger);
		$result = $smoobu->getApartments();

		self::assertNull($result);
	}

	public function testEmptyFiltersArray(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getReservations([]);

		self::assertNull($result);
	}

	public function testValidationHappensBeforeApiCall(): void
	{
		// Should not attempt API call if validation fails
		$this->logger->expects(self::once())
			->method('error')
			->with('Invalid apartment ID. Must be a positive integer.');

		$result = $this->smoobu->getApartment(-100);

		self::assertNull($result);
	}

	public function testBoundaryValueForPositiveIds(): void
	{
		$this->logger->expects(self::once())
			->method('error')
			->with('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');

		$smoobu = new Smoobu(null, null, $this->logger);
		$result = $smoobu->getApartment(1);

		self::assertNull($result);
	}

	public function testMethodChainingDoesNotBreakState(): void
	{
		$smoobu = new Smoobu();
		$result = $smoobu->setApiKey('key1')->setApiKey('key2')->setApiKey('key3');

		self::assertSame($smoobu, $result);
	}
}
