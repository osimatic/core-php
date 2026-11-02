<?php

namespace Osimatic\Route;

use Osimatic\Network\HTTPClient;
use Osimatic\Network\HTTPMethod;
use Osimatic\Network\HTTPRequestExecutor;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Route calculation client based on the Google Routes API (computeRouteMatrix method).
 * It replaces the legacy Distance Matrix API, which can no longer be enabled on new Google Cloud projects, and supports real-time traffic for driving routes.
 * @link https://developers.google.com/maps/documentation/routes/reference/rest/v2/TopLevel/computeRouteMatrix Routes API computeRouteMatrix reference
 */
class GoogleRoutesMatrix
{
	/** Endpoint of the computeRouteMatrix method */
	public const string API_URL = 'https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix';

	/** Response fields requested to the API; the field mask is mandatory and must contain status to detect failed routes */
	private const string FIELD_MASK = 'originIndex,destinationIndex,duration,distanceMeters,status,condition';

	/** Condition returned by the API when a route exists between the two points */
	private const string CONDITION_ROUTE_EXISTS = 'ROUTE_EXISTS';

	/** HTTP request executor for making API calls */
	private HTTPRequestExecutor $requestExecutor;

	/**
	 * @param string|null $apiKey The Google API key for authentication (can be set later via setApiKey())
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 * @param ClientInterface $httpClient The PSR-18 HTTP client instance used for making API requests (default: HTTPClient)
	 */
	public function __construct(
		private ?string $apiKey = null,
		private LoggerInterface $logger = new NullLogger(),
		ClientInterface $httpClient = new HTTPClient(),
	)
	{
		$this->requestExecutor = new HTTPRequestExecutor($httpClient, $logger);
	}

	/**
	 * Sets the Google API key for authenticating requests.
	 * @param string $apiKey The Google API key to use for Routes API requests
	 * @return self Returns this instance for method chaining
	 */
	public function setApiKey(string $apiKey): self
	{
		$this->apiKey = $apiKey;
		return $this;
	}

	// ========================================
	// Public Methods
	// ========================================

	/**
	 * Retrieves the duration and the distance of a route between two locations.
	 * When traffic is enabled in the parameters, the duration takes real-time traffic into account (driving mode only).
	 * @param string $originCoordinates Origin coordinates in format "latitude,longitude" (e.g., "48.8566,2.3522")
	 * @param string $destinationCoordinates Destination coordinates in format "latitude,longitude" (e.g., "51.5074,-0.1278")
	 * @param TravelMode $travelMode The travel mode to use (DRIVE, TRANSIT, WALK or BICYCLE). Default: TravelMode::DRIVE
	 * @param RouteParameters $parameters Routing options (transit modes, tolls, traffic)
	 * @return array|null Returns [duration_in_seconds, distance_in_meters] on success, null on failure or invalid travel mode
	 */
	public function getDistanceMatrix(string $originCoordinates, string $destinationCoordinates, TravelMode $travelMode = TravelMode::DRIVE, RouteParameters $parameters = new RouteParameters()): ?array
	{
		if (empty($this->apiKey)) {
			$this->logger->error('Google Routes API key is missing');
			return null;
		}

		$routesTravelMode = match ($travelMode) {
			TravelMode::DRIVE => 'DRIVE',
			TravelMode::TRANSIT => 'TRANSIT',
			TravelMode::WALK => 'WALK',
			TravelMode::BICYCLE => 'BICYCLE',
			default => null,
		};
		if (null === $routesTravelMode) {
			$this->logger->error('Invalid travel mode provided', ['travelMode' => $travelMode->value]);
			return null;
		}

		$origin = self::parseCoordinates($originCoordinates);
		$destination = self::parseCoordinates($destinationCoordinates);
		if (null === $origin || null === $destination) {
			$this->logger->error('Invalid coordinates provided', ['origin' => $originCoordinates, 'destination' => $destinationCoordinates]);
			return null;
		}

		$body = [
			'origins' => [['waypoint' => ['location' => ['latLng' => $origin]]]],
			'destinations' => [['waypoint' => ['location' => ['latLng' => $destination]]]],
			'travelMode' => $routesTravelMode,
		];

		// routingPreference and routeModifiers are only accepted for driving routes
		if (TravelMode::DRIVE === $travelMode) {
			$body['routingPreference'] = $parameters->isTrafficAware() ? 'TRAFFIC_AWARE' : 'TRAFFIC_UNAWARE';
			if ($parameters->isAvoidTolls()) {
				$body['routeModifiers'] = ['avoidTolls' => true];
			}
		}

		// Transit mode names of the enum match the Routes API values (BUS, SUBWAY, TRAIN, LIGHT_RAIL)
		if (TravelMode::TRANSIT === $travelMode && !empty($transitModes = $parameters->getTransitModes())) {
			$body['transitPreferences'] = [
				'allowedTravelModes' => array_values(array_unique(array_map(static fn(TransitTravelMode $transitMode) => $transitMode->value, $transitModes))),
			];
		}

		$json = $this->requestExecutor->execute(HTTPMethod::POST, self::API_URL, $body, [
			'X-Goog-Api-Key' => $this->apiKey,
			'X-Goog-FieldMask' => self::FIELD_MASK,
		], jsonBody: true, decodeJson: true);

		if (!is_array($json) || !is_array($json[0] ?? null)) {
			$this->logger->error('Invalid response structure from Google Routes API', ['error' => $json['error']['message'] ?? null]);
			return null;
		}

		$element = $json[0];

		if (self::CONDITION_ROUTE_EXISTS !== ($element['condition'] ?? null) || !isset($element['duration'])) {
			$this->logger->warning('Google Routes API could not calculate route', [
				'condition' => $element['condition'] ?? 'unknown',
				'origin' => $originCoordinates,
				'destination' => $destinationCoordinates,
			]);
			return null;
		}

		return [self::parseDuration($element['duration']), (int) ($element['distanceMeters'] ?? 0)];
	}

	// ========================================
	// Helper Methods
	// ========================================

	/**
	 * Converts a "latitude,longitude" string into the latLng object expected by the Routes API.
	 * @param string $coordinates Coordinates in format "latitude,longitude"
	 * @return array{latitude: float, longitude: float}|null The latLng object, or null if the coordinates are invalid
	 */
	private static function parseCoordinates(string $coordinates): ?array
	{
		$parts = explode(',', str_replace(' ', '', $coordinates));
		if (2 !== count($parts) || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
			return null;
		}

		return ['latitude' => (float) $parts[0], 'longitude' => (float) $parts[1]];
	}

	/**
	 * Converts a Routes API duration (e.g., "1234s") into seconds.
	 * @param string $duration The duration returned by the API, with the "s" suffix
	 * @return int The duration in seconds
	 * @link https://developers.google.com/maps/documentation/routes/reference/rest/v2/TopLevel/computeRouteMatrix#RouteMatrixElement Duration format
	 */
	private static function parseDuration(string $duration): int
	{
		return (int) round((float) rtrim($duration, 's'));
	}
}