<?php

declare(strict_types=1);

namespace Tests\Location;

use GuzzleHttp\Psr7\Response;
use Osimatic\Location\GoogleMaps;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
final class GoogleMapsTest extends TestCase
{
	private const string TEST_API_KEY = 'test-google-maps-api-key';
	private const string TEST_ADDRESS = '10 Downing Street, London, UK';
	private const string TEST_COORDINATES = '51.5033635,-0.1276248';
	private const float TEST_LATITUDE = 51.5033635;
	private const float TEST_LONGITUDE = -0.1276248;

	/**
	 * Helper method to create a PSR-7 Response with JSON body
	 * @param array $data Data to encode as JSON
	 * @param int $statusCode HTTP status code
	 * @return Response PSR-7 Response instance
	 */
	private function createJsonResponse(array $data, int $statusCode = 200): Response
	{
		return new Response($statusCode, ['Content-Type' => 'application/json'], json_encode($data));
	}

	/**
	 * Helper method to create a successful geocoding response
	 * @param string $address The address
	 * @param float $lat Latitude
	 * @param float $lng Longitude
	 * @return array The geocoding response array
	 */
	private function createGeocodingResponse(string $address, float $lat, float $lng): array
	{
		return [
			'status' => 'OK',
			'results' => [
				[
					'formatted_address' => $address,
					'geometry' => [
						'location' => [
							'lat' => $lat,
							'lng' => $lng
						]
					],
					'address_components' => [
						[
							'long_name' => '10',
							'short_name' => '10',
							'types' => ['street_number']
						],
						[
							'long_name' => 'Downing Street',
							'short_name' => 'Downing St',
							'types' => ['route']
						],
						[
							'long_name' => 'London',
							'short_name' => 'London',
							'types' => ['locality', 'political']
						],
						[
							'long_name' => 'SW1A 2AA',
							'short_name' => 'SW1A 2AA',
							'types' => ['postal_code']
						],
						[
							'long_name' => 'United Kingdom',
							'short_name' => 'GB',
							'types' => ['country', 'political']
						]
					]
				]
			]
		];
	}

	/* ===================== Constants ===================== */

	public function testApiUrlConstant(): void
	{
		self::assertSame('https://maps.googleapis.com/maps/api/', GoogleMaps::API_URL);
	}

	/* ===================== Constructor ===================== */

	public function testConstructorWithAllParameters(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$httpClient = $this->createMock(ClientInterface::class);
		$googleMaps = new GoogleMaps(self::TEST_API_KEY, logger: $logger, httpClient: $httpClient);

		self::assertInstanceOf(GoogleMaps::class, $googleMaps);
	}

	public function testConstructorWithMinimalParameters(): void
	{
		$googleMaps = new GoogleMaps();

		self::assertInstanceOf(GoogleMaps::class, $googleMaps);
	}

	public function testConstructorWithCustomCacheTtl(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient, cacheTtl: 7200);

		self::assertInstanceOf(GoogleMaps::class, $googleMaps);
	}

	/* ===================== Setters ===================== */

	public function testSetApiKey(): void
	{
		$googleMaps = new GoogleMaps();

		$result = $googleMaps->setApiKey('new-api-key');

		self::assertSame($googleMaps, $result);
	}

	public function testFluentInterface(): void
	{
		$googleMaps = new GoogleMaps();

		$result = $googleMaps->setApiKey(self::TEST_API_KEY);

		self::assertSame($googleMaps, $result);
	}

	/* ===================== Static method: getUrl() ===================== */

	public function testGetUrl(): void
	{
		$url = GoogleMaps::getUrl(self::TEST_COORDINATES);

		self::assertStringContainsString('maps.google.com', $url);
		self::assertStringContainsString('51.5033635', $url);
		self::assertStringContainsString('-0.1276248', $url);
	}

	/* ===================== geocoding() ===================== */

	public function testGeocodingWithoutApiKey(): void
	{
		$googleMaps = new GoogleMaps();

		$result = $googleMaps->geocoding(self::TEST_ADDRESS);

		self::assertNull($result);
	}

	public function testGeocodingWithValidApiKey(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->geocoding(self::TEST_ADDRESS);

		self::assertNotNull($result);
		self::assertIsArray($result);
		self::assertCount(1, $result);
	}

	public function testGeocodingVerifiesRequestContainsAddress(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'geocode/json')
					&& str_contains($uri, 'key=' . self::TEST_API_KEY)
					&& str_contains($uri, 'address=');
			}))
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->geocoding(self::TEST_ADDRESS);

		self::assertNotNull($result);
	}

	public function testGeocodingWithNetworkException(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willThrowException(new class('Network error') extends \RuntimeException implements ClientExceptionInterface {});

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->geocoding(self::TEST_ADDRESS);

		self::assertNull($result);
	}

	public function testGeocodingWithRequestDenied(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(self::stringContains('Access denied'));

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse([
				'status' => 'REQUEST_DENIED',
				'error_message' => 'The provided API key is invalid.'
			]));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, logger: $logger, httpClient: $httpClient);

		$result = $googleMaps->geocoding(self::TEST_ADDRESS);

		self::assertNull($result);
	}

	public function testGeocodingWithOverQueryLimit(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(self::stringContains('Quota exceeded'));

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse([
				'status' => 'OVER_QUERY_LIMIT',
				'error_message' => 'You have exceeded your daily request quota.'
			]));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, logger: $logger, httpClient: $httpClient);

		$result = $googleMaps->geocoding(self::TEST_ADDRESS);

		self::assertNull($result);
	}

	public function testGeocodingWithInvalidRequest(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(self::stringContains('Invalid request'));

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse([
				'status' => 'INVALID_REQUEST',
				'error_message' => 'Invalid request'
			]));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, logger: $logger, httpClient: $httpClient);

		$result = $googleMaps->geocoding(self::TEST_ADDRESS);

		self::assertNull($result);
	}

	public function testGeocodingWithZeroResults(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('info')
			->with('No results found.');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse([
				'status' => 'ZERO_RESULTS'
			]));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, logger: $logger, httpClient: $httpClient);

		$result = $googleMaps->geocoding('NonexistentPlace123');

		self::assertNull($result);
	}

	/* ===================== Cache tests ===================== */

	public function testGeocodingUsesCache(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once()) // Only called once, second call uses cache
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		// First call - hits API
		$result1 = $googleMaps->geocoding(self::TEST_ADDRESS);
		// Second call - uses cache
		$result2 = $googleMaps->geocoding(self::TEST_ADDRESS);

		self::assertNotNull($result1);
		self::assertNotNull($result2);
		self::assertSame($result1, $result2);
	}

	public function testClearCache(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::exactly(2)) // Called twice after cache clear
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient, cacheTtl: 3600);

		// First call - hits API
		$result1 = $googleMaps->geocoding(self::TEST_ADDRESS);

		// Clear cache
		$googleMaps->clearCache();

		// Second call - hits API again since cache was cleared
		$result2 = $googleMaps->geocoding(self::TEST_ADDRESS);

		self::assertNotNull($result1);
		self::assertNotNull($result2);
	}

	/* ===================== reverseGeocoding() ===================== */

	public function testReverseGeocodingWithoutApiKey(): void
	{
		$googleMaps = new GoogleMaps();

		$result = $googleMaps->reverseGeocoding(self::TEST_COORDINATES);

		self::assertNull($result);
	}

	public function testReverseGeocodingWithValidCoordinates(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->reverseGeocoding(self::TEST_COORDINATES);

		self::assertNotNull($result);
		self::assertIsArray($result);
	}

	public function testReverseGeocodingFromLatitudeAndLongitude(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'latlng=');
			}))
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->reverseGeocodingFromLatitudeAndLongitude(self::TEST_LATITUDE, self::TEST_LONGITUDE);

		self::assertNotNull($result);
	}

	/* ===================== getCoordinatesFromAddress() ===================== */

	public function testGetCoordinatesFromAddress(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getCoordinatesFromAddress(self::TEST_ADDRESS);

		self::assertNotNull($result);
		self::assertIsString($result);
		self::assertStringContainsString('51.5033635', $result);
		self::assertStringContainsString('-0.1276248', $result);
	}

	public function testGetCoordinatesFromAddressReturnsNullOnError(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse([
				'status' => 'ZERO_RESULTS'
			]));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getCoordinatesFromAddress('Invalid Address');

		self::assertNull($result);
	}

	/* ===================== getFormattedAddressFromAddress() ===================== */

	public function testGetFormattedAddressFromAddress(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getFormattedAddressFromAddress(self::TEST_ADDRESS);

		self::assertNotNull($result);
		self::assertIsString($result);
	}

	/* ===================== getFormattedAddressFromCoordinates() ===================== */

	public function testGetFormattedAddressFromCoordinates(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getFormattedAddressFromCoordinates(self::TEST_COORDINATES);

		self::assertNotNull($result);
		self::assertIsString($result);
	}

	/* ===================== Static methods ===================== */

	public function testGetCoordinatesFromResult(): void
	{
		$result = [
			'geometry' => [
				'location' => [
					'lat' => 48.8566,
					'lng' => 2.3522
				]
			]
		];

		$coordinates = GoogleMaps::getCoordinatesFromResult($result);

		self::assertNotNull($coordinates);
		self::assertStringContainsString('48.8566', $coordinates);
		self::assertStringContainsString('2.3522', $coordinates);
	}

	public function testGetCoordinatesFromResultReturnsNullWithMissingData(): void
	{
		$result = ['geometry' => []];

		$coordinates = GoogleMaps::getCoordinatesFromResult($result);

		self::assertNull($coordinates);
	}

	public function testGetFormattedAddressFromResult(): void
	{
		$result = [
			'formatted_address' => '10 Downing Street, London SW1A 2AA, UK'
		];

		$formattedAddress = GoogleMaps::getFormattedAddressFromResult($result);

		self::assertNotNull($formattedAddress);
		self::assertIsString($formattedAddress);
	}

	public function testGetFormattedAddressFromResultReturnsNullWithMissingData(): void
	{
		$result = [];

		$formattedAddress = GoogleMaps::getFormattedAddressFromResult($result);

		self::assertNull($formattedAddress);
	}

	public function testGetAddressComponentsFromResult(): void
	{
		$result = [
			'formatted_address' => '10 Downing Street, London',
			'address_components' => [
				[
					'long_name' => '10',
					'short_name' => '10',
					'types' => ['street_number']
				],
				[
					'long_name' => 'Downing Street',
					'short_name' => 'Downing St',
					'types' => ['route']
				],
				[
					'long_name' => 'London',
					'short_name' => 'London',
					'types' => ['locality']
				],
				[
					'long_name' => 'SW1A 2AA',
					'short_name' => 'SW1A 2AA',
					'types' => ['postal_code']
				],
				[
					'long_name' => 'United Kingdom',
					'short_name' => 'GB',
					'types' => ['country']
				]
			]
		];

		$addressComponents = GoogleMaps::getAddressComponentsFromResult($result);

		self::assertNotNull($addressComponents);
		self::assertIsArray($addressComponents);
		self::assertArrayHasKey('street', $addressComponents);
		self::assertArrayHasKey('locality', $addressComponents);
		self::assertArrayHasKey('postal_code', $addressComponents);
		self::assertArrayHasKey('country', $addressComponents);
		self::assertArrayHasKey('country_code', $addressComponents);
		self::assertSame('London', $addressComponents['locality']);
		self::assertSame('SW1A 2AA', $addressComponents['postal_code']);
		self::assertSame('GB', $addressComponents['country_code']);
	}

	public function testGetAddressComponentsFromResultReturnsNullWithMissingData(): void
	{
		$result = [];

		$addressComponents = GoogleMaps::getAddressComponentsFromResult($result);

		self::assertNull($addressComponents);
	}

	/* ===================== Credentials Validation ===================== */

	public function testGeocodingLogsErrorWithoutApiKey(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with('Google Maps API key is not configured. Please set API key.');

		$googleMaps = new GoogleMaps(logger: $logger);

		$result = $googleMaps->geocoding(self::TEST_ADDRESS);

		self::assertNull($result);
	}

	/* ===================== Special characters handling ===================== */

	public function testGeocodingWithSpecialCharacters(): void
	{
		$addressWithSpecialChars = "Rue de l'Église, Paris";
		$responseData = $this->createGeocodingResponse($addressWithSpecialChars, 48.8566, 2.3522);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->geocoding($addressWithSpecialChars);

		self::assertNotNull($result);
	}

	/* ===================== Workflow tests ===================== */

	public function testCompleteGeocodingWorkflow(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(httpClient: $httpClient);
		$googleMaps->setApiKey(self::TEST_API_KEY);

		$results = $googleMaps->geocoding(self::TEST_ADDRESS);
		$coordinates = GoogleMaps::getCoordinatesFromResult($results[0]);
		$formattedAddress = GoogleMaps::getFormattedAddressFromResult($results[0]);

		self::assertNotNull($results);
		self::assertNotNull($coordinates);
		self::assertNotNull($formattedAddress);
	}

	/* ===================== PostalAddress methods ===================== */

	public function testInitPostalAddressDataFromAddress(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$postalAddress = $this->createMock(\Osimatic\Location\PostalAddressInterface::class);
		$postalAddress->expects(self::once())->method('setRoad');
		$postalAddress->expects(self::once())->method('setPostcode');
		$postalAddress->expects(self::once())->method('setCity');
		$postalAddress->expects(self::once())->method('setCountryCode');
		$postalAddress->expects(self::once())->method('setCoordinates');
		$postalAddress->expects(self::once())->method('setFormattedAddress');

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->initPostalAddressDataFromAddress($postalAddress, self::TEST_ADDRESS);

		self::assertTrue($result);
	}

	public function testInitPostalAddressDataFromAddressReturnsFalseOnError(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse(['status' => 'ZERO_RESULTS']));

		$postalAddress = $this->createMock(\Osimatic\Location\PostalAddressInterface::class);

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->initPostalAddressDataFromAddress($postalAddress, 'Invalid Address');

		self::assertFalse($result);
	}

	public function testInitPostalAddressDataFromCoordinates(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$postalAddress = $this->createMock(\Osimatic\Location\PostalAddressInterface::class);
		$postalAddress->expects(self::once())->method('setRoad');
		$postalAddress->expects(self::once())->method('setPostcode');
		$postalAddress->expects(self::once())->method('setCity');
		$postalAddress->expects(self::once())->method('setCountryCode');
		$postalAddress->expects(self::exactly(2))->method('setCoordinates'); // Once in initPostalAddressFromResult, once explicitly
		$postalAddress->expects(self::once())->method('setFormattedAddress');

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->initPostalAddressDataFromCoordinates($postalAddress, self::TEST_COORDINATES);

		self::assertTrue($result);
	}

	public function testInitPostalAddressDataFromCoordinatesReturnsFalseOnNullResults(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse(['status' => 'ZERO_RESULTS']));

		$postalAddress = $this->createMock(\Osimatic\Location\PostalAddressInterface::class);

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->initPostalAddressDataFromCoordinates($postalAddress, self::TEST_COORDINATES);

		self::assertFalse($result);
	}

	public function testInitPostalAddressDataFromCoordinatesReturnsFalseOnMissingFormattedAddress(): void
	{
		$responseData = [
			'status' => 'OK',
			'results' => [
				[
					'geometry' => [
						'location' => [
							'lat' => self::TEST_LATITUDE,
							'lng' => self::TEST_LONGITUDE
						]
					],
					// Missing formatted_address field
					'address_components' => []
				]
			]
		];

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$postalAddress = $this->createMock(\Osimatic\Location\PostalAddressInterface::class);

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->initPostalAddressDataFromCoordinates($postalAddress, self::TEST_COORDINATES);

		self::assertFalse($result);
	}

	public function testInitPostalAddressFromResult(): void
	{
		$result = [
			'formatted_address' => self::TEST_ADDRESS,
			'geometry' => [
				'location' => [
					'lat' => self::TEST_LATITUDE,
					'lng' => self::TEST_LONGITUDE
				]
			],
			'address_components' => [
				[
					'long_name' => 'Downing Street',
					'types' => ['route']
				],
				[
					'long_name' => 'London',
					'types' => ['locality']
				],
				[
					'long_name' => 'SW1A 2AA',
					'types' => ['postal_code']
				],
				[
					'short_name' => 'GB',
					'types' => ['country']
				]
			]
		];

		$postalAddress = $this->createMock(\Osimatic\Location\PostalAddressInterface::class);
		$postalAddress->expects(self::once())->method('setRoad')->with(self::isString());
		$postalAddress->expects(self::once())->method('setPostcode')->with('SW1A 2AA');
		$postalAddress->expects(self::once())->method('setCity')->with('London');
		$postalAddress->expects(self::once())->method('setCountryCode')->with('GB');
		$postalAddress->expects(self::once())->method('setCoordinates')->with(self::isString());
		$postalAddress->expects(self::once())->method('setFormattedAddress')->with(self::isString());

		$success = GoogleMaps::initPostalAddressFromResult($postalAddress, $result);

		self::assertTrue($success);
	}

	public function testInitPostalAddressFromResultReturnsFalseOnMissingCoordinates(): void
	{
		$result = [
			'formatted_address' => self::TEST_ADDRESS,
			'geometry' => [], // Missing location
			'address_components' => []
		];

		$postalAddress = $this->createMock(\Osimatic\Location\PostalAddressInterface::class);

		$success = GoogleMaps::initPostalAddressFromResult($postalAddress, $result);

		self::assertFalse($success);
	}

	public function testGetCoordinatesFromPostalAddressWithFullAddress(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$postalAddress = $this->createMock(\Osimatic\Location\PostalAddressInterface::class);
		$postalAddress->method('getRoad')->willReturn('10 Downing Street');
		$postalAddress->method('getAttention')->willReturn('Prime Minister');
		$postalAddress->method('getPostcode')->willReturn('SW1A 2AA');
		$postalAddress->method('getCity')->willReturn('London');
		$postalAddress->method('getCountryCode')->willReturn('GB');

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getCoordinatesFromPostalAddress($postalAddress);

		self::assertNotNull($result);
		self::assertIsString($result);
	}

	public function testGetCoordinatesFromPostalAddressWithoutAttention(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$postalAddress = $this->createMock(\Osimatic\Location\PostalAddressInterface::class);
		$postalAddress->method('getRoad')->willReturn('10 Downing Street');
		$postalAddress->method('getAttention')->willReturn(null);
		$postalAddress->method('getPostcode')->willReturn('SW1A 2AA');
		$postalAddress->method('getCity')->willReturn('London');
		$postalAddress->method('getCountryCode')->willReturn('GB');

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getCoordinatesFromPostalAddress($postalAddress);

		self::assertNotNull($result);
	}

	public function testGetCoordinatesFromPostalAddressWithAttentionFallback(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		// Called twice: first with road+attention fails (returns null), second with attention succeeds
		$httpClient->expects(self::exactly(2))
			->method('sendRequest')
			->willReturnOnConsecutiveCalls(
				$this->createJsonResponse(['status' => 'ZERO_RESULTS']), // First call fails
				$this->createJsonResponse($responseData) // Second call succeeds
			);

		$postalAddress = $this->createMock(\Osimatic\Location\PostalAddressInterface::class);
		$postalAddress->method('getRoad')->willReturn('Unknown Street');
		$postalAddress->method('getAttention')->willReturn('Prime Minister Office');
		$postalAddress->method('getPostcode')->willReturn('SW1A 2AA');
		$postalAddress->method('getCity')->willReturn('London');
		$postalAddress->method('getCountryCode')->willReturn('GB');

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getCoordinatesFromPostalAddress($postalAddress);

		self::assertNotNull($result);
	}

	public function testGetCoordinatesFromPostalAddressReturnsNullWithoutRoadAndCity(): void
	{
		$postalAddress = $this->createMock(\Osimatic\Location\PostalAddressInterface::class);
		$postalAddress->method('getRoad')->willReturn(null);
		$postalAddress->method('getCity')->willReturn(null);

		$googleMaps = new GoogleMaps(self::TEST_API_KEY);

		$result = $googleMaps->getCoordinatesFromPostalAddress($postalAddress);

		self::assertNull($result);
	}

	public function testGetCoordinatesFromPostalAddressUsesDefaultCountryCode(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'France'); // Default country is FR = France
			}))
			->willReturn($this->createJsonResponse($responseData));

		$postalAddress = $this->createMock(\Osimatic\Location\PostalAddressInterface::class);
		$postalAddress->method('getRoad')->willReturn('10 Rue de Rivoli');
		$postalAddress->method('getPostcode')->willReturn('75001');
		$postalAddress->method('getCity')->willReturn('Paris');
		$postalAddress->method('getCountryCode')->willReturn(null); // No country code

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getCoordinatesFromPostalAddress($postalAddress);

		self::assertNotNull($result);
	}

	/* ===================== Deprecated methods ===================== */

	public function testGetAddressDataFromAddress(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getAddressDataFromAddress(self::TEST_ADDRESS);

		self::assertNotNull($result);
		self::assertIsArray($result);
		self::assertArrayHasKey('coordinates', $result);
		self::assertArrayHasKey('formatted_address', $result);
		self::assertArrayHasKey('address_components', $result);
	}

	public function testGetAddressDataFromAddressReturnsNullOnError(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse(['status' => 'ZERO_RESULTS']));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getAddressDataFromAddress('Invalid');

		self::assertNull($result);
	}

	public function testGetAddressDataFromAddressReturnsNullOnMissingCoordinates(): void
	{
		$responseData = [
			'status' => 'OK',
			'results' => [
				[
					'formatted_address' => self::TEST_ADDRESS,
					'geometry' => [], // Missing location
					'address_components' => []
				]
			]
		];

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getAddressDataFromAddress(self::TEST_ADDRESS);

		self::assertNull($result);
	}

	public function testGetAddressDataFromCoordinates(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getAddressDataFromCoordinates(self::TEST_COORDINATES);

		self::assertNotNull($result);
		self::assertIsArray($result);
		self::assertArrayHasKey('coordinates', $result);
		self::assertArrayHasKey('formatted_address', $result);
		self::assertArrayHasKey('address_components', $result);
		self::assertSame(self::TEST_COORDINATES, $result['coordinates']);
	}

	public function testGetAddressDataFromCoordinatesReturnsNullOnError(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse(['status' => 'ZERO_RESULTS']));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getAddressDataFromCoordinates(self::TEST_COORDINATES);

		self::assertNull($result);
	}

	public function testGetAddressDataFromCoordinatesReturnsNullOnMissingFormattedAddress(): void
	{
		$responseData = [
			'status' => 'OK',
			'results' => [
				[
					'geometry' => [
						'location' => [
							'lat' => self::TEST_LATITUDE,
							'lng' => self::TEST_LONGITUDE
						]
					],
					// Missing formatted_address
					'address_components' => []
				]
			]
		];

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getAddressDataFromCoordinates(self::TEST_COORDINATES);

		self::assertNull($result);
	}

	public function testGetAddressComponentsFromAddress(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getAddressComponentsFromAddress(self::TEST_ADDRESS);

		self::assertNotNull($result);
		self::assertIsArray($result);
		self::assertArrayHasKey('street', $result);
		self::assertArrayHasKey('locality', $result);
	}

	public function testGetAddressComponentsFromAddressReturnsNullOnError(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse(['status' => 'ZERO_RESULTS']));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getAddressComponentsFromAddress('Invalid');

		self::assertNull($result);
	}

	public function testGetAddressComponentsFromCoordinates(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getAddressComponentsFromCoordinates(self::TEST_COORDINATES);

		self::assertNotNull($result);
		self::assertIsArray($result);
		self::assertArrayHasKey('street', $result);
		self::assertArrayHasKey('locality', $result);
	}

	public function testGetAddressComponentsFromCoordinatesReturnsNullOnError(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse(['status' => 'ZERO_RESULTS']));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		$result = $googleMaps->getAddressComponentsFromCoordinates(self::TEST_COORDINATES);

		self::assertNull($result);
	}

	/* ===================== Error handling tests ===================== */

	public function testGeocodingWithUnknownError(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(self::stringContains('Error during Google API request'));

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse([
				'status' => 'UNKNOWN_ERROR',
				'error_message' => 'An unknown error occurred'
			]));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, logger: $logger, httpClient: $httpClient);

		$result = $googleMaps->geocoding(self::TEST_ADDRESS);

		self::assertNull($result);
	}

	public function testGeocodingWithErrorStatus(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(self::stringContains('Error during Google API request'));

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse([
				'status' => 'ERROR',
				'error_message' => 'Generic error'
			]));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, logger: $logger, httpClient: $httpClient);

		$result = $googleMaps->geocoding(self::TEST_ADDRESS);

		self::assertNull($result);
	}

	public function testGeocodingWithMissingResults(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('info')
			->with('No results found.');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn($this->createJsonResponse([
				'status' => 'OK'
				// Missing 'results' field
			]));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, logger: $logger, httpClient: $httpClient);

		$result = $googleMaps->geocoding(self::TEST_ADDRESS);

		self::assertNull($result);
	}

	/* ===================== Address components variations ===================== */

	public function testGetAddressComponentsWithSuburb(): void
	{
		$result = [
			'formatted_address' => 'Test Address',
			'address_components' => [
				[
					'long_name' => 'Montmartre',
					'types' => ['sublocality_level_1']
				]
			]
		];

		$addressComponents = GoogleMaps::getAddressComponentsFromResult($result);

		self::assertNotNull($addressComponents);
		self::assertArrayHasKey('suburb', $addressComponents);
		self::assertSame('Montmartre', $addressComponents['suburb']);
	}

	public function testGetAddressComponentsWithPostalTown(): void
	{
		$result = [
			'formatted_address' => 'Test Address',
			'address_components' => [
				[
					'long_name' => 'Reading',
					'types' => ['postal_town']
				]
			]
		];

		$addressComponents = GoogleMaps::getAddressComponentsFromResult($result);

		self::assertNotNull($addressComponents);
		self::assertArrayHasKey('locality', $addressComponents);
		self::assertSame('Reading', $addressComponents['locality']);
	}

	public function testGetAddressComponentsWithAdministrativeAreas(): void
	{
		$result = [
			'formatted_address' => 'Test Address',
			'address_components' => [
				[
					'long_name' => 'California',
					'types' => ['administrative_area_level_1']
				],
				[
					'long_name' => 'Los Angeles County',
					'types' => ['administrative_area_level_2']
				],
				[
					'long_name' => 'District 3',
					'types' => ['administrative_area_level_3']
				]
			]
		];

		$addressComponents = GoogleMaps::getAddressComponentsFromResult($result);

		self::assertNotNull($addressComponents);
		self::assertArrayHasKey('administrative_area_level_1', $addressComponents);
		self::assertArrayHasKey('administrative_area_level_2', $addressComponents);
		self::assertSame('California', $addressComponents['administrative_area_level_1']);
		self::assertSame('Los Angeles County', $addressComponents['administrative_area_level_2']);
		self::assertSame('District 3', $addressComponents['locality']); // admin_area_level_3 maps to locality
	}

	public function testGetAddressComponentsStreetExtraction(): void
	{
		$result = [
			'formatted_address' => '131, Avenue Charles de Gaulle, 92200 Neuilly-sur-Seine, France',
			'address_components' => [
				[
					'long_name' => '131',
					'types' => ['street_number']
				],
				[
					'long_name' => 'Avenue Charles de Gaulle',
					'types' => ['route']
				]
			]
		];

		$addressComponents = GoogleMaps::getAddressComponentsFromResult($result);

		self::assertNotNull($addressComponents);
		self::assertArrayHasKey('street', $addressComponents);
		self::assertStringContainsString('131', $addressComponents['street']);
		self::assertStringContainsString('Avenue Charles de Gaulle', $addressComponents['street']);
	}

	public function testGetAddressComponentsStreetWithoutRouteInFormattedAddress(): void
	{
		$result = [
			'formatted_address' => 'Some Building, Paris, France',
			'address_components' => [
				[
					'long_name' => '42',
					'types' => ['street_number']
				],
				[
					'long_name' => 'Unknown Street',
					'types' => ['route']
				]
			]
		];

		$addressComponents = GoogleMaps::getAddressComponentsFromResult($result);

		self::assertNotNull($addressComponents);
		self::assertArrayHasKey('street', $addressComponents);
		// Should fallback to concatenation since route is not in formatted_address
		self::assertSame('42 Unknown Street', $addressComponents['street']);
	}

	public function testReverseGeocodingUsesCache(): void
	{
		$responseData = $this->createGeocodingResponse(self::TEST_ADDRESS, self::TEST_LATITUDE, self::TEST_LONGITUDE);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once()) // Only called once, second call uses cache
			->method('sendRequest')
			->willReturn($this->createJsonResponse($responseData));

		$googleMaps = new GoogleMaps(self::TEST_API_KEY, httpClient: $httpClient);

		// First call - hits API
		$result1 = $googleMaps->reverseGeocoding(self::TEST_COORDINATES);
		// Second call - uses cache
		$result2 = $googleMaps->reverseGeocoding(self::TEST_COORDINATES);

		self::assertNotNull($result1);
		self::assertNotNull($result2);
		self::assertSame($result1, $result2);
	}
}