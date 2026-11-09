<?php

namespace Tests\Route;

use GuzzleHttp\Psr7\Response;
use Osimatic\Route\GoogleDistanceMatrix;
use Osimatic\Route\RouteParameters;
use Osimatic\Route\TransitTravelMode;
use Osimatic\Route\TravelMode;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class GoogleDistanceMatrixTest extends TestCase
{
	private const string TEST_API_KEY = 'test-api-key';
	private const string ORIGIN_COORDS = '48.8566,2.3522'; // Paris
	private const string DESTINATION_COORDS = '51.5074,-0.1278'; // London

	// ========== Constructor Tests ==========

	public function testConstructorWithoutApiKey(): void
	{
		$client = new GoogleDistanceMatrix();
		self::assertInstanceOf(GoogleDistanceMatrix::class, $client);
	}

	public function testConstructorWithApiKey(): void
	{
		$client = new GoogleDistanceMatrix(self::TEST_API_KEY);
		self::assertInstanceOf(GoogleDistanceMatrix::class, $client);
	}

	public function testConstructorWithApiKeyAndLogger(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, $logger);
		self::assertInstanceOf(GoogleDistanceMatrix::class, $client);
	}

	public function testConstructorWithAllParameters(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$httpClient = $this->createMock(ClientInterface::class);
		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, $logger, $httpClient);
		self::assertInstanceOf(GoogleDistanceMatrix::class, $client);
	}

	// ========== setApiKey Tests ==========

	public function testSetApiKey(): void
	{
		$client = new GoogleDistanceMatrix();
		$result = $client->setApiKey('my-api-key');
		self::assertSame($client, $result); // Test fluent interface
	}

	public function testSetApiKeyReplacesExistingKey(): void
	{
		$client = new GoogleDistanceMatrix('old-key');
		$client->setApiKey('new-key');
		self::assertInstanceOf(GoogleDistanceMatrix::class, $client);
	}

	// ========== Successful API Calls ==========

	public function testGetDistanceMatrixSuccessfulResponse(): void
	{
		$responseData = [
			'status' => 'OK',
			'rows' => [
				[
					'elements' => [
						[
							'status' => 'OK',
							'duration' => ['value' => 3600], // 1 hour in seconds
							'distance' => ['value' => 100000], // 100 km in meters
						],
					],
				],
			],
		];

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS);

		self::assertIsArray($result);
		self::assertCount(2, $result);
		self::assertSame(3600, $result[0]); // duration
		self::assertSame(100000, $result[1]); // distance
	}

	public function testGetDistanceMatrixWithCompleteResponseStructure(): void
	{
		$responseData = [
			'status' => 'OK',
			'origin_addresses' => ['Paris, France'],
			'destination_addresses' => ['London, UK'],
			'rows' => [
				[
					'elements' => [
						[
							'status' => 'OK',
							'duration' => [
								'value' => 7200,
								'text' => '2 hours',
							],
							'distance' => [
								'value' => 450000,
								'text' => '450 km',
							],
						],
					],
				],
			],
		];

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS);

		self::assertSame([7200, 450000], $result);
	}

	// ========== Missing API Key ==========

	public function testGetDistanceMatrixWithoutApiKeyReturnsNull(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with('Google Distance Matrix API key is missing');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::never())->method('sendRequest');

		$client = new GoogleDistanceMatrix(null, $logger, $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS);

		self::assertNull($result);
	}

	// ========== Invalid Travel Mode ==========

	public function testGetDistanceMatrixWithInvalidTravelModeReturnsNull(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(
				'Invalid travel mode provided',
				self::callback(function ($context) {
					return isset($context['travelMode']);
				})
			);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::never())->method('sendRequest');

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, $logger, $httpClient);

		// Using an unsupported travel mode (PLANE is not mapped in the match statement)
		$result = $client->getDistanceMatrix(
			self::ORIGIN_COORDS,
			self::DESTINATION_COORDS,
			TravelMode::PLANE
		);

		self::assertNull($result);
	}

	// ========== Different Travel Modes ==========

	public function testGetDistanceMatrixWithDriveMode(): void
	{
		$responseData = $this->createSuccessResponse(5400, 200000);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'mode=driving');
			}))
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::DRIVE);

		self::assertSame([5400, 200000], $result);
	}

	public function testGetDistanceMatrixWithTransitMode(): void
	{
		$responseData = $this->createSuccessResponse(7200, 180000);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'mode=transit');
			}))
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::TRANSIT);

		self::assertSame([7200, 180000], $result);
	}

	public function testGetDistanceMatrixWithWalkMode(): void
	{
		$responseData = $this->createSuccessResponse(36000, 30000);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'mode=walking');
			}))
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::WALK);

		self::assertSame([36000, 30000], $result);
	}

	public function testGetDistanceMatrixWithBicycleMode(): void
	{
		$responseData = $this->createSuccessResponse(10800, 40000);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'mode=bicycling');
			}))
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::BICYCLE);

		self::assertSame([10800, 40000], $result);
	}

	// ========== Parameters: Avoid Options for Drive Mode ==========

	public function testGetDistanceMatrixAvoidTollsForDriveMode(): void
	{
		$responseData = $this->createSuccessResponse(6000, 210000);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'avoid=tolls');
			}))
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$params = new RouteParameters();
		$params->avoidTolls();

		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::DRIVE, $params);

		self::assertSame([6000, 210000], $result);
	}

	public function testGetDistanceMatrixAvoidHighwaysForDriveMode(): void
	{
		$responseData = $this->createSuccessResponse(7200, 195000);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'avoid=highways');
			}))
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$params = new RouteParameters();
		$params->avoidHighways();

		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::DRIVE, $params);

		self::assertSame([7200, 195000], $result);
	}

	public function testGetDistanceMatrixAvoidFerriesForDriveMode(): void
	{
		$responseData = $this->createSuccessResponse(5800, 205000);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'avoid=ferries');
			}))
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$params = new RouteParameters();
		$params->avoidFerries();

		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::DRIVE, $params);

		self::assertSame([5800, 205000], $result);
	}

	public function testGetDistanceMatrixAvoidMultipleForDriveMode(): void
	{
		$responseData = $this->createSuccessResponse(8100, 220000);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'avoid=tolls') &&
					   str_contains($uri, 'highways') &&
					   str_contains($uri, 'ferries');
			}))
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$params = new RouteParameters();
		$params->avoidTolls()->avoidHighways()->avoidFerries();

		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::DRIVE, $params);

		self::assertSame([8100, 220000], $result);
	}

	// ========== Parameters: Avoid Indoor for Walk Mode ==========

	public function testGetDistanceMatrixAvoidIndoorForWalkMode(): void
	{
		$responseData = $this->createSuccessResponse(36300, 30500);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'avoid=indoor');
			}))
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$params = new RouteParameters();
		$params->avoidIndoor();

		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::WALK, $params);

		self::assertSame([36300, 30500], $result);
	}

	// ========== Parameters: Transit Modes ==========

	public function testGetDistanceMatrixWithTransitModes(): void
	{
		$responseData = $this->createSuccessResponse(7500, 185000);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'transit_mode=bus') && str_contains($uri, 'subway');
			}))
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$params = new RouteParameters();
		$params->setTransitModes([TransitTravelMode::BUS, TransitTravelMode::SUBWAY]);

		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::TRANSIT, $params);

		self::assertSame([7500, 185000], $result);
	}

	public function testGetDistanceMatrixWithAllTransitModes(): void
	{
		$responseData = $this->createSuccessResponse(7000, 180000);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				return str_contains($uri, 'transit_mode=') &&
					   str_contains($uri, 'bus') &&
					   str_contains($uri, 'subway') &&
					   str_contains($uri, 'train') &&
					   str_contains($uri, 'tram');
			}))
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$params = new RouteParameters();
		$params->setTransitModes([
			TransitTravelMode::BUS,
			TransitTravelMode::SUBWAY,
			TransitTravelMode::TRAIN,
			TransitTravelMode::LIGHT_RAIL,
		]);

		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::TRANSIT, $params);

		self::assertSame([7000, 180000], $result);
	}

	// ========== Coordinates Formatting ==========

	public function testGetDistanceMatrixRemovesSpacesFromCoordinates(): void
	{
		$responseData = $this->createSuccessResponse(3600, 100000);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(function ($request) {
				$uri = (string) $request->getUri();
				// Check that spaces are removed
				return str_contains($uri, 'origins=48.8566%2C2.3522') &&
					   str_contains($uri, 'destinations=51.5074%2C-0.1278');
			}))
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$result = $client->getDistanceMatrix('48.8566, 2.3522', '51.5074, -0.1278');

		self::assertSame([3600, 100000], $result);
	}

	// ========== API Error Responses ==========

	public function testGetDistanceMatrixWithApiErrorStatus(): void
	{
		$responseData = [
			'status' => 'REQUEST_DENIED',
			'error_message' => 'The provided API key is invalid.',
		];

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(
				'Google Distance Matrix API returned error status',
				self::callback(function ($context) {
					return $context['status'] === 'REQUEST_DENIED' &&
						   isset($context['error_message']);
				})
			);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, $logger, $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS);

		self::assertNull($result);
	}

	public function testGetDistanceMatrixWithInvalidResponseStructure(): void
	{
		$responseData = [
			'status' => 'OK',
			'rows' => [], // Empty rows
		];

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with('Invalid response structure from Google Distance Matrix API');

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, $logger, $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS);

		self::assertNull($result);
	}

	public function testGetDistanceMatrixWithElementNotOkStatus(): void
	{
		$responseData = [
			'status' => 'OK',
			'rows' => [
				[
					'elements' => [
						[
							'status' => 'ZERO_RESULTS',
						],
					],
				],
			],
		];

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('warning')
			->with(
				'Google Distance Matrix API could not calculate route',
				self::callback(function ($context) {
					return $context['status'] === 'ZERO_RESULTS' &&
						   isset($context['origin']) &&
						   isset($context['destination']);
				})
			);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, $logger, $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS);

		self::assertNull($result);
	}

	// ========== HTTP Errors ==========

	public function testGetDistanceMatrixWithHttpError(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::exactly(2))
			->method('error')
			->with(self::logicalOr(
				self::stringContains('JSON decoding'),
				self::stringContains('Failed to fetch data from Google Distance Matrix API')
			));

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn(new Response(500, [], 'Internal Server Error'));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, $logger, $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS);

		self::assertNull($result);
	}

	public function testGetDistanceMatrixWithInvalidJson(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::exactly(2))
			->method('error')
			->with(self::logicalOr(
				self::stringContains('JSON decoding'),
				self::stringContains('Failed to fetch data from Google Distance Matrix API')
			));

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn(new Response(200, [], 'invalid json'));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, $logger, $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS);

		self::assertNull($result);
	}

	public function testGetDistanceMatrixWithNetworkException(): void
	{
		$exception = new class('Network error') extends \RuntimeException implements ClientExceptionInterface {};

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::exactly(2))
			->method('error')
			->with(self::logicalOr(
				self::stringContains('HTTP request failed'),
				self::stringContains('Failed to fetch data from Google Distance Matrix API')
			));

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willThrowException($exception);

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, $logger, $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS);

		self::assertNull($result);
	}

	// ========== Edge Cases ==========

	public function testGetDistanceMatrixWithEmptyCoordinates(): void
	{
		$responseData = ['status' => 'INVALID_REQUEST'];

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(
				'Google Distance Matrix API returned error status',
				self::anything()
			);

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->method('sendRequest')
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, $logger, $httpClient);
		$result = $client->getDistanceMatrix('', '');

		self::assertNull($result);
	}

	public function testGetDistanceMatrixWithMissingDurationValue(): void
	{
		$responseData = [
			'status' => 'OK',
			'rows' => [
				[
					'elements' => [
						[
							'status' => 'OK',
							'distance' => ['value' => 100000],
							// duration missing
						],
					],
				],
			],
		];

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS);

		self::assertSame([0, 100000], $result); // duration defaults to 0
	}

	public function testGetDistanceMatrixWithMissingDistanceValue(): void
	{
		$responseData = [
			'status' => 'OK',
			'rows' => [
				[
					'elements' => [
						[
							'status' => 'OK',
							'duration' => ['value' => 3600],
							// distance missing
						],
					],
				],
			],
		];

		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->willReturn(new Response(200, [], json_encode($responseData)));

		$client = new GoogleDistanceMatrix(self::TEST_API_KEY, httpClient: $httpClient);
		$result = $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS);

		self::assertSame([3600, 0], $result); // distance defaults to 0
	}

	// ========== Fluent Interface ==========

	public function testFluentInterfaceWithSetters(): void
	{
		$client = new GoogleDistanceMatrix();
		$result = $client->setApiKey('test-key');
		self::assertSame($client, $result);
	}

	// ========== Helper Methods ==========

	private function createSuccessResponse(int $duration, int $distance): array
	{
		return [
			'status' => 'OK',
			'rows' => [
				[
					'elements' => [
						[
							'status' => 'OK',
							'duration' => ['value' => $duration],
							'distance' => ['value' => $distance],
						],
					],
				],
			],
		];
	}
}