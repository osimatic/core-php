<?php

namespace Tests\Route;

use GuzzleHttp\Psr7\Response;
use Osimatic\Route\GoogleRoutesMatrix;
use Osimatic\Route\RouteParameters;
use Osimatic\Route\TransitTravelMode;
use Osimatic\Route\TravelMode;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

#[AllowMockObjectsWithoutExpectations]
class GoogleRoutesMatrixTest extends TestCase
{
	private const string TEST_API_KEY = 'test-api-key';
	private const string ORIGIN_COORDS = '48.8566,2.3522'; // Paris
	private const string DESTINATION_COORDS = '51.5074,-0.1278'; // London

	// ========== Constructor Tests ==========

	public function testConstructor(): void
	{
		self::assertInstanceOf(GoogleRoutesMatrix::class, new GoogleRoutesMatrix());
		self::assertInstanceOf(GoogleRoutesMatrix::class, new GoogleRoutesMatrix(self::TEST_API_KEY));
		self::assertInstanceOf(GoogleRoutesMatrix::class, new GoogleRoutesMatrix(self::TEST_API_KEY, new NullLogger(), $this->createHttpClientMock('[]', $request, 200, 0)));
	}

	// ========== setApiKey Tests ==========

	public function testSetApiKey(): void
	{
		$client = new GoogleRoutesMatrix();
		self::assertSame($client, $client->setApiKey('my-api-key')); // Test fluent interface
	}

	// ========== getDistanceMatrix Tests ==========

	public function testGetDistanceMatrix(): void
	{
		$request = null;

		// Missing or empty API key: no request is sent
		self::assertNull((new GoogleRoutesMatrix(null, new NullLogger(), $this->createHttpClientMock('[]', $request, 200, 0)))->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::DRIVE));
		self::assertNull((new GoogleRoutesMatrix('', new NullLogger(), $this->createHttpClientMock('[]', $request, 200, 0)))->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::DRIVE));

		// Travel mode not supported by the Routes API: no request is sent
		self::assertNull((new GoogleRoutesMatrix(self::TEST_API_KEY, new NullLogger(), $this->createHttpClientMock('[]', $request, 200, 0)))->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::PLANE));

		// Invalid coordinates: no request is sent
		self::assertNull((new GoogleRoutesMatrix(self::TEST_API_KEY, new NullLogger(), $this->createHttpClientMock('[]', $request, 200, 0)))->getDistanceMatrix('invalid', self::DESTINATION_COORDS, TravelMode::DRIVE));

		// Driving with traffic and avoiding tolls: traffic-aware routing preference and toll avoidance are sent, duration is read from the response
		$response = '[{"originIndex":0,"destinationIndex":0,"duration":"1234s","distanceMeters":5000,"status":{},"condition":"ROUTE_EXISTS"}]';
		$client = new GoogleRoutesMatrix(self::TEST_API_KEY, new NullLogger(), $this->createHttpClientMock($response, $request));
		$parameters = (new RouteParameters())->setTrafficAware(true)->setAvoidTolls(true);
		self::assertSame([1234, 5000], $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::DRIVE, $parameters));
		self::assertSame('POST', $request->getMethod());
		self::assertSame(GoogleRoutesMatrix::API_URL, (string) $request->getUri());
		self::assertSame([self::TEST_API_KEY], $request->getHeader('X-Goog-Api-Key'));
		self::assertSame(['originIndex,destinationIndex,duration,distanceMeters,status,condition'], $request->getHeader('X-Goog-FieldMask'));
		self::assertEquals([
			'origins' => [['waypoint' => ['location' => ['latLng' => ['latitude' => 48.8566, 'longitude' => 2.3522]]]]],
			'destinations' => [['waypoint' => ['location' => ['latLng' => ['latitude' => 51.5074, 'longitude' => -0.1278]]]]],
			'travelMode' => 'DRIVE',
			'routingPreference' => 'TRAFFIC_AWARE',
			'routeModifiers' => ['avoidTolls' => true],
		], $this->decodeRequestBody($request));

		// Driving without traffic and without toll avoidance: traffic-unaware routing preference, no route modifiers, missing distance defaults to 0
		$response = '[{"originIndex":0,"destinationIndex":0,"duration":"600s","status":{},"condition":"ROUTE_EXISTS"}]';
		$client = new GoogleRoutesMatrix(self::TEST_API_KEY, new NullLogger(), $this->createHttpClientMock($response, $request));
		self::assertSame([600, 0], $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::DRIVE, new RouteParameters()));
		$body = $this->decodeRequestBody($request);
		self::assertSame('TRAFFIC_UNAWARE', $body['routingPreference']);
		self::assertArrayNotHasKey('routeModifiers', $body);

		// Walking: no routing preference and no route modifiers are sent (only accepted for driving)
		$response = '[{"originIndex":0,"destinationIndex":0,"duration":"60s","distanceMeters":80,"status":{},"condition":"ROUTE_EXISTS"}]';
		$client = new GoogleRoutesMatrix(self::TEST_API_KEY, new NullLogger(), $this->createHttpClientMock($response, $request));
		self::assertSame([60, 80], $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::WALK, (new RouteParameters())->setTrafficAware(true)->setAvoidTolls(true)));
		$body = $this->decodeRequestBody($request);
		self::assertSame('WALK', $body['travelMode']);
		self::assertArrayNotHasKey('routingPreference', $body);
		self::assertArrayNotHasKey('routeModifiers', $body);

		// Transit: preferred transit modes are sent without duplicates
		$response = '[{"originIndex":0,"destinationIndex":0,"duration":"100s","distanceMeters":200,"status":{},"condition":"ROUTE_EXISTS"}]';
		$client = new GoogleRoutesMatrix(self::TEST_API_KEY, new NullLogger(), $this->createHttpClientMock($response, $request));
		$parameters = (new RouteParameters())->setTransitModes([TransitTravelMode::BUS, TransitTravelMode::SUBWAY, TransitTravelMode::BUS]);
		self::assertSame([100, 200], $client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::TRANSIT, $parameters));
		$body = $this->decodeRequestBody($request);
		self::assertSame('TRANSIT', $body['travelMode']);
		self::assertSame(['BUS', 'SUBWAY'], $body['transitPreferences']['allowedTravelModes']);
		self::assertArrayNotHasKey('routingPreference', $body);

		// Route not found: null
		$response = '[{"originIndex":0,"destinationIndex":0,"status":{"code":5,"message":"not found"},"condition":"ROUTE_NOT_FOUND"}]';
		$client = new GoogleRoutesMatrix(self::TEST_API_KEY, new NullLogger(), $this->createHttpClientMock($response, $request));
		self::assertNull($client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::DRIVE));

		// API error response (e.g., invalid key): null
		$response = '{"error":{"code":403,"message":"API key not valid","status":"PERMISSION_DENIED"}}';
		$client = new GoogleRoutesMatrix(self::TEST_API_KEY, new NullLogger(), $this->createHttpClientMock($response, $request, 403));
		self::assertNull($client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::DRIVE));

		// Response body that is not JSON: null
		$client = new GoogleRoutesMatrix(self::TEST_API_KEY, new NullLogger(), $this->createHttpClientMock('not json', $request));
		self::assertNull($client->getDistanceMatrix(self::ORIGIN_COORDS, self::DESTINATION_COORDS, TravelMode::DRIVE));
	}

	// ========== Helper Methods ==========

	/**
	 * Creates a PSR-18 client mock returning the given body and capturing the sent request.
	 * @param string $responseBody The body returned by the API
	 * @param RequestInterface|null $capturedRequest Receives the last request sent to the API
	 * @param int $statusCode The HTTP status code returned by the API
	 * @param int $expectedCalls The number of requests expected to be sent
	 * @return ClientInterface&MockObject
	 */
	private function createHttpClientMock(string $responseBody, ?RequestInterface &$capturedRequest = null, int $statusCode = 200, int $expectedCalls = 1): ClientInterface&MockObject
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::exactly($expectedCalls))
			->method('sendRequest')
			->willReturnCallback(function (RequestInterface $request) use (&$capturedRequest, $responseBody, $statusCode) {
				$capturedRequest = $request;
				return new Response($statusCode, [], $responseBody);
			});
		return $httpClient;
	}

	/**
	 * Decodes the JSON body of a captured request.
	 * @param RequestInterface|null $request The captured request
	 * @return array The decoded body
	 */
	private function decodeRequestBody(?RequestInterface $request): array
	{
		return json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
	}
}