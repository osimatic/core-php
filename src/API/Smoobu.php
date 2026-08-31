<?php

namespace Osimatic\API;

use Osimatic\Network\HTTPClient;
use Osimatic\Network\HTTPMethod;
use Osimatic\Network\HTTPRequestExecutor;
use Osimatic\Network\URL;
use Osimatic\Text\UUID;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Client for interacting with the Smoobu API.
 * Smoobu is a vacation rental management platform that provides property management, booking management, channel management, and guest communication features.
 * This class provides methods to manage apartments/properties, reservations, rates, guests, messages, and more.
 *
 * Each request is authenticated using HMAC-SHA256 signing (the legacy "Api-Key" header authentication has been removed, as it is discontinued by Smoobu on 2026-09-25).
 * The following headers are sent with every request:
 *   X-API-Key   : Smoobu public API key (e.g. usr_live_abc123)
 *   X-Timestamp : Current UTC timestamp in ISO 8601 format (e.g. 2026-04-01T12:00:00Z)
 *   X-Nonce     : Unique UUID v4 generated per request
 *   X-Signature : Base64-encoded HMAC-SHA256 signature of the canonical request string
 *
 * Canonical string format:
 *   METHOD\n/path\nsorted_query_string\nTIMESTAMP\nNONCE\nBODY_SHA256_HASH\nAPI_KEY
 *
 * @link https://docs.smoobu.com/ Official Smoobu API documentation
 * @link https://www.smoobu.com/ Smoobu website
 */
class Smoobu
{
	// ========================================
	// Constants
	// ========================================

	/** Base URL for the Smoobu API endpoint */
	public const string API_URL = 'https://login.smoobu.com';

	/** Maximum requests allowed per minute (as per API documentation) */
	public const int RATE_LIMIT_PER_MINUTE = 1000;

	// ========================================
	// Properties
	// ========================================

	/** HTTP request executor for making API calls */
	private HTTPRequestExecutor $requestExecutor;

	// ========================================
	// Constructor & Configuration
	// ========================================

	/**
	 * Initializes a new Smoobu API client instance.
	 *
	 * @param string|null $apiKey The Smoobu public API key used for HMAC authentication
	 * @param string|null $apiSecret The Smoobu API secret used to sign requests (HMAC-SHA256)
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 * @param ClientInterface $httpClient The PSR-18 HTTP client instance used for making API requests (default: HTTPClient)
	 */
	public function __construct(
		private ?string $apiKey = null,
		private ?string $apiSecret = null,
		private readonly LoggerInterface $logger = new NullLogger(),
		ClientInterface $httpClient = new HTTPClient(),
	)
	{
		$this->requestExecutor = new HTTPRequestExecutor($httpClient, $logger);
	}

	/**
	 * Sets the Smoobu API key used for HMAC authentication.
	 *
	 * @param string $apiKey The Smoobu public API key
	 * @return self Returns this instance for method chaining
	 */
	public function setApiKey(string $apiKey): self
	{
		$this->apiKey = $apiKey;
		return $this;
	}

	/**
	 * Sets the Smoobu API secret used to sign requests (HMAC-SHA256).
	 *
	 * @param string $apiSecret The Smoobu API secret
	 * @return self Returns this instance for method chaining
	 */
	public function setApiSecret(string $apiSecret): self
	{
		$this->apiSecret = $apiSecret;
		return $this;
	}

	// ========================================
	// User Methods
	// ========================================

	/**
	 * Retrieves the current authenticated user profile information.
	 *
	 * @link https://docs.smoobu.com/ User endpoint documentation
	 * @return array|null The user profile data if successful, null on failure
	 */
	public function getMe(): ?array
	{
		return $this->sendRequest('/api/me', HTTPMethod::GET);
	}

	// ========================================
	// Apartments/Properties Methods
	// ========================================

	/**
	 * Retrieves a list of all apartments/properties.
	 *
	 * @link https://docs.smoobu.com/ Apartments endpoint documentation
	 * @return array|null Array of apartments if successful, null on failure
	 */
	public function getApartments(): ?array
	{
		return $this->sendRequest('/api/apartments', HTTPMethod::GET);
	}

	/**
	 * Retrieves detailed information about a specific apartment/property.
	 * Returns comprehensive data including location, rooms, amenities, and pricing information.
	 *
	 * @param int $apartmentId The unique identifier of the apartment
	 * @return array|null The apartment details if successful, null on failure
	 */
	public function getApartment(int $apartmentId): ?array
	{
		if ($apartmentId <= 0) {
			$this->logger->error('Invalid apartment ID. Must be a positive integer.');
			return null;
		}

		return $this->sendRequest('/api/apartments/' . $apartmentId, HTTPMethod::GET);
	}

	// ========================================
	// Reservations Methods
	// ========================================

	/**
	 * Retrieves a list of reservations with optional filtering.
	 *
	 * @param array $filters Optional filters for the reservation list. Supported keys:
	 *                       - 'from' (string): Start date for filtering (YYYY-MM-DD format)
	 *                       - 'to' (string): End date for filtering (YYYY-MM-DD format)
	 *                       - 'arrivalFrom' (string): Filter by arrival date from (YYYY-MM-DD format)
	 *                       - 'arrivalTo' (string): Filter by arrival date to (YYYY-MM-DD format)
	 *                       - 'departureFrom' (string): Filter by departure date from (YYYY-MM-DD format)
	 *                       - 'departureTo' (string): Filter by departure date to (YYYY-MM-DD format)
	 *                       - 'modifiedFrom' (string): Filter by modification date from (YYYY-MM-DD format)
	 *                       - 'modifiedTo' (string): Filter by modification date to (YYYY-MM-DD format)
	 *                       - 'apartmentId' (int): Filter by specific apartment ID
	 *                       - 'includeRelated' (bool): Include related data
	 * @link https://docs.smoobu.com/ Reservations endpoint documentation
	 * @return array|null Array of reservations if successful, null on failure
	 */
	public function getReservations(array $filters = []): ?array
	{
		return $this->sendRequest('/api/reservations', HTTPMethod::GET, $filters);
	}

	/**
	 * Retrieves detailed information about a specific reservation.
	 *
	 * @param int $reservationId The unique identifier of the reservation
	 * @return array|null The reservation details if successful, null on failure
	 */
	public function getReservation(int $reservationId): ?array
	{
		if ($reservationId <= 0) {
			$this->logger->error('Invalid reservation ID. Must be a positive integer.');
			return null;
		}

		return $this->sendRequest('/api/reservations/' . $reservationId, HTTPMethod::GET);
	}

	/**
	 * Creates a new reservation.
	 *
	 * @param array $reservationData Reservation data. Required keys:
	 *                               - 'arrivalDate' (string): Check-in date (YYYY-MM-DD format)
	 *                               - 'departureDate' (string): Check-out date (YYYY-MM-DD format)
	 *                               - 'apartmentId' (int): The apartment ID for the booking
	 *                               Optional keys:
	 *                               - 'firstname' (string): Guest first name
	 *                               - 'lastname' (string): Guest last name
	 *                               - 'email' (string): Guest email address
	 *                               - 'phone' (string): Guest phone number
	 *                               - 'adults' (int): Number of adults
	 *                               - 'children' (int): Number of children
	 *                               - 'price' (float): Total price for the reservation
	 *                               - 'prepayment' (float): Prepayment amount
	 *                               - 'deposit' (float): Deposit amount
	 *                               - 'notice' (string): Internal notes
	 *                               - 'guestNotice' (string): Notes visible to guest
	 *                               - 'channel' (string): Booking channel/source
	 *                               - 'reference' (string): External reference ID
	 * @link https://docs.smoobu.com/ Create reservation documentation
	 * @return array|null The created reservation data if successful, null on failure
	 */
	public function createReservation(array $reservationData): ?array
	{
		if (!isset($reservationData['arrivalDate'], $reservationData['departureDate'], $reservationData['apartmentId'])) {
			$this->logger->error('Missing required fields: arrivalDate, departureDate, and apartmentId are required.');
			return null;
		}

		return $this->sendRequest('/api/reservations', HTTPMethod::POST, [], $reservationData);
	}

	/**
	 * Updates an existing reservation.
	 *
	 * @param int $reservationId The unique identifier of the reservation to update
	 * @param array $reservationData Updated reservation data (same structure as createReservation)
	 * @return array|null The updated reservation data if successful, null on failure
	 */
	public function updateReservation(int $reservationId, array $reservationData): ?array
	{
		if ($reservationId <= 0) {
			$this->logger->error('Invalid reservation ID. Must be a positive integer.');
			return null;
		}

		return $this->sendRequest('/api/reservations/' . $reservationId, HTTPMethod::PUT, [], $reservationData);
	}

	/**
	 * Cancels a reservation.
	 *
	 * @param int $reservationId The unique identifier of the reservation to cancel
	 * @return bool True if the reservation was successfully cancelled, false on failure
	 */
	public function cancelReservation(int $reservationId): bool
	{
		if ($reservationId <= 0) {
			$this->logger->error('Invalid reservation ID. Must be a positive integer.');
			return false;
		}

		$result = $this->sendRequest('/api/reservations/' . $reservationId, HTTPMethod::DELETE);
		return $result !== null;
	}

	/**
	 * Alias for cancelReservation method.
	 *
	 * @param int $reservationId The unique identifier of the reservation to delete
	 * @deprecated Use cancelReservation() instead
	 * @return bool True if the reservation was successfully deleted, false on failure
	 */
	public function deleteReservation(int $reservationId): bool
	{
		return $this->cancelReservation($reservationId);
	}

	// ========================================
	// Availability & Rates Methods
	// ========================================

	/**
	 * Checks apartment availability for specific date ranges.
	 *
	 * @param array $checkData Check availability data. Required keys:
	 *                         - 'arrivalDate' (string): Check-in date (YYYY-MM-DD format)
	 *                         - 'departureDate' (string): Check-out date (YYYY-MM-DD format)
	 *                         Optional keys:
	 *                         - 'apartmentId' (int): Specific apartment ID to check
	 *                         - 'adults' (int): Number of adults
	 *                         - 'children' (int): Number of children
	 * @link https://docs.smoobu.com/ Availability check documentation
	 * @return array|null Array of available apartments if successful, null on failure
	 */
	public function checkAvailability(array $checkData): ?array
	{
		if (!isset($checkData['arrivalDate'], $checkData['departureDate'])) {
			$this->logger->error('Missing required fields: arrivalDate and departureDate are required.');
			return null;
		}

		return $this->sendRequest('/booking/checkApartmentAvailability', HTTPMethod::POST, [], $checkData);
	}

	/**
	 * Retrieves pricing rates for apartments within date ranges.
	 *
	 * @param array $filters Optional filters for rates. Supported keys:
	 *                       - 'apartmentId' (int): Filter by specific apartment ID
	 *                       - 'from' (string): Start date for filtering (YYYY-MM-DD format)
	 *                       - 'to' (string): End date for filtering (YYYY-MM-DD format)
	 * @link https://docs.smoobu.com/ Rates endpoint documentation
	 * @return array|null Array of rates if successful, null on failure
	 */
	public function getRates(array $filters = []): ?array
	{
		return $this->sendRequest('/api/rates', HTTPMethod::GET, $filters);
	}

	/**
	 * Creates or updates pricing rates for an apartment.
	 *
	 * @param array $rateData Rate data. Required keys:
	 *                        - 'apartmentId' (int): The apartment ID
	 *                        - 'startDate' (string): Rate start date (YYYY-MM-DD format)
	 *                        - 'endDate' (string): Rate end date (YYYY-MM-DD format)
	 *                        - 'price' (float): Price per night
	 *                        Optional keys:
	 *                        - 'minimumStay' (int): Minimum stay requirement in nights
	 *                        - 'available' (bool): Availability status
	 * @link https://docs.smoobu.com/ Create/update rates documentation
	 * @return array|null The created/updated rate data if successful, null on failure
	 */
	public function createRate(array $rateData): ?array
	{
		if (!isset($rateData['apartmentId'], $rateData['startDate'], $rateData['endDate'], $rateData['price'])) {
			$this->logger->error('Missing required fields: apartmentId, startDate, endDate, and price are required.');
			return null;
		}

		return $this->sendRequest('/api/rates', HTTPMethod::POST, [], $rateData);
	}

	/**
	 * Alias for createRate method (since the API uses the same endpoint for create and update).
	 *
	 * @param array $rateData Rate data (same structure as createRate)
	 * @return array|null The updated rate data if successful, null on failure
	 */
	public function updateRate(array $rateData): ?array
	{
		return $this->createRate($rateData);
	}

	// ========================================
	// Price Elements Methods
	// ========================================

	/**
	 * Retrieves the pricing breakdown for a specific reservation.
	 *
	 * @param int $reservationId The unique identifier of the reservation
	 * @return array|null Array of price elements if successful, null on failure
	 */
	public function getPriceElements(int $reservationId): ?array
	{
		if ($reservationId <= 0) {
			$this->logger->error('Invalid reservation ID. Must be a positive integer.');
			return null;
		}

		return $this->sendRequest('/api/reservations/' . $reservationId . '/price-elements', HTTPMethod::GET);
	}

	/**
	 * Adds a price element (charge/fee) to a reservation.
	 *
	 * @param int $reservationId The unique identifier of the reservation
	 * @param array $priceElementData Price element data. Required keys:
	 *                                - 'type' (string): Type of charge (e.g., 'cleaning', 'addon', 'fee')
	 *                                - 'name' (string): Name/description of the charge
	 *                                - 'price' (float): Amount to charge
	 *                                Optional keys:
	 *                                - 'vatPercent' (float): VAT percentage
	 *                                - 'isPaid' (bool): Payment status
	 * @link https://docs.smoobu.com/ Price elements documentation
	 * @return array|null The created price element if successful, null on failure
	 */
	public function addPriceElement(int $reservationId, array $priceElementData): ?array
	{
		if ($reservationId <= 0) {
			$this->logger->error('Invalid reservation ID. Must be a positive integer.');
			return null;
		}

		if (!isset($priceElementData['type'], $priceElementData['name'], $priceElementData['price'])) {
			$this->logger->error('Missing required fields: type, name, and price are required.');
			return null;
		}

		return $this->sendRequest('/api/reservations/' . $reservationId . '/price-elements', HTTPMethod::POST, [], $priceElementData);
	}

	/**
	 * Updates a specific price element for a reservation.
	 *
	 * @param int $reservationId The unique identifier of the reservation
	 * @param int $priceElementId The unique identifier of the price element to update
	 * @param array $priceElementData Updated price element data (same structure as addPriceElement)
	 * @return array|null The updated price element if successful, null on failure
	 */
	public function updatePriceElement(int $reservationId, int $priceElementId, array $priceElementData): ?array
	{
		if ($reservationId <= 0 || $priceElementId <= 0) {
			$this->logger->error('Invalid reservation ID or price element ID. Must be positive integers.');
			return null;
		}

		return $this->sendRequest('/api/reservations/' . $reservationId . '/price-elements/' . $priceElementId, HTTPMethod::POST, [], $priceElementData);
	}

	/**
	 * Removes a price element from a reservation.
	 *
	 * @param int $reservationId The unique identifier of the reservation
	 * @param int $priceElementId The unique identifier of the price element to remove
	 * @return bool True if the price element was successfully removed, false on failure
	 */
	public function deletePriceElement(int $reservationId, int $priceElementId): bool
	{
		if ($reservationId <= 0 || $priceElementId <= 0) {
			$this->logger->error('Invalid reservation ID or price element ID. Must be positive integers.');
			return false;
		}

		$result = $this->sendRequest('/api/reservations/' . $reservationId . '/price-elements/' . $priceElementId, HTTPMethod::DELETE);
		return $result !== null;
	}

	// ========================================
	// Guests Methods
	// ========================================

	/**
	 * Retrieves a paginated list of guests with their booking history.
	 *
	 * @param array $filters Optional filters for the guest list. Supported keys:
	 *                       - 'page' (int): Page number for pagination (default: 1)
	 *                       - 'pageSize' (int): Number of results per page (default: 100)
	 * @link https://docs.smoobu.com/ Guests endpoint documentation
	 * @return array|null Array of guests if successful, null on failure
	 */
	public function getGuests(array $filters = []): ?array
	{
		return $this->sendRequest('/api/guests', HTTPMethod::GET, $filters);
	}

	/**
	 * Retrieves detailed information about a specific guest, including all their reservations.
	 *
	 * @param int $guestId The unique identifier of the guest
	 * @return array|null The guest details with reservation history if successful, null on failure
	 */
	public function getGuest(int $guestId): ?array
	{
		if ($guestId <= 0) {
			$this->logger->error('Invalid guest ID. Must be a positive integer.');
			return null;
		}

		return $this->sendRequest('/api/guests/' . $guestId, HTTPMethod::GET);
	}

	// ========================================
	// Messages Methods
	// ========================================

	/**
	 * Retrieves message conversation history for a specific reservation.
	 *
	 * @param int $reservationId The unique identifier of the reservation
	 * @return array|null Array of messages if successful, null on failure
	 */
	public function getMessages(int $reservationId): ?array
	{
		if ($reservationId <= 0) {
			$this->logger->error('Invalid reservation ID. Must be a positive integer.');
			return null;
		}

		return $this->sendRequest('/api/reservations/' . $reservationId . '/messages', HTTPMethod::GET);
	}

	/**
	 * Retrieves all message threads with the latest updates.
	 *
	 * @link https://docs.smoobu.com/ Message threads documentation
	 * @return array|null Array of message threads if successful, null on failure
	 */
	public function getMessageThreads(): ?array
	{
		return $this->sendRequest('/api/threads', HTTPMethod::GET);
	}

	/**
	 * Sends a message to the guest for a specific reservation.
	 *
	 * @param int $reservationId The unique identifier of the reservation
	 * @param string $message The message content to send to the guest
	 * @param array $options Optional message options. Supported keys:
	 *                       - 'subject' (string): Message subject line
	 *                       - 'sendCopy' (bool): Send a copy to the host
	 * @link https://docs.smoobu.com/ Send message documentation
	 * @return array|null The sent message data if successful, null on failure
	 */
	public function sendMessageToGuest(int $reservationId, string $message, array $options = []): ?array
	{
		if ($reservationId <= 0) {
			$this->logger->error('Invalid reservation ID. Must be a positive integer.');
			return null;
		}

		if (empty($message)) {
			$this->logger->error('Message content cannot be empty.');
			return null;
		}

		$messageData = array_merge(['message' => $message], $options);

		return $this->sendRequest('/api/reservations/' . $reservationId . '/messages/send-message-to-guest', HTTPMethod::POST, [], $messageData);
	}

	/**
	 * Sends an internal message to the host for a specific reservation.
	 *
	 * @param int $reservationId The unique identifier of the reservation
	 * @param string $message The message content to send internally
	 * @param array $options Optional message options. Supported keys:
	 *                       - 'subject' (string): Message subject line
	 * @return array|null The sent message data if successful, null on failure
	 */
	public function sendMessageToHost(int $reservationId, string $message, array $options = []): ?array
	{
		if ($reservationId <= 0) {
			$this->logger->error('Invalid reservation ID. Must be a positive integer.');
			return null;
		}

		if (empty($message)) {
			$this->logger->error('Message content cannot be empty.');
			return null;
		}

		$messageData = array_merge(['message' => $message], $options);

		return $this->sendRequest('/api/reservations/' . $reservationId . '/messages/send-message-to-host', HTTPMethod::POST, [], $messageData);
	}

	// ========================================
	// Addons Methods
	// ========================================

	/**
	 * Retrieves a list of all available addons/extras.
	 *
	 * @link https://docs.smoobu.com/ Addons endpoint documentation
	 * @return array|null Array of addons if successful, null on failure
	 */
	public function getAddons(): ?array
	{
		return $this->sendRequest('/api/addons', HTTPMethod::GET);
	}

	/**
	 * Retrieves addons/extras available for a specific apartment.
	 *
	 * @param int $apartmentId The unique identifier of the apartment
	 * @return array|null Array of apartment-specific addons if successful, null on failure
	 */
	public function getApartmentAddons(int $apartmentId): ?array
	{
		if ($apartmentId <= 0) {
			$this->logger->error('Invalid apartment ID. Must be a positive integer.');
			return null;
		}

		return $this->sendRequest('/api/addons/' . $apartmentId, HTTPMethod::GET);
	}

	// ========================================
	// Placeholders Methods (Beta)
	// ========================================

	/**
	 * Retrieves available template placeholders/variables for a specific reservation.
	 * These placeholders can be used in automated messages and templates.
	 *
	 * @param int $reservationId The unique identifier of the reservation
	 * @link https://docs.smoobu.com/ Placeholders documentation
	 * @return array|null Array of available placeholders if successful, null on failure
	 */
	public function getReservationPlaceholders(int $reservationId): ?array
	{
		if ($reservationId <= 0) {
			$this->logger->error('Invalid reservation ID. Must be a positive integer.');
			return null;
		}

		return $this->sendRequest('/api/reservations/' . $reservationId . '/placeholders', HTTPMethod::GET);
	}

	/**
	 * Retrieves all custom placeholders with multi-language support.
	 *
	 * @return array|null Array of custom placeholders if successful, null on failure
	 */
	public function getCustomPlaceholders(): ?array
	{
		return $this->sendRequest('/api/custom-placeholders', HTTPMethod::GET);
	}

	/**
	 * Creates a new custom placeholder for use in templates.
	 *
	 * @param array $placeholderData Placeholder data. Required keys:
	 *                               - 'name' (string): Placeholder name/key
	 *                               - 'value' (string): Placeholder default value
	 *                               Optional keys:
	 *                               - 'translations' (array): Multi-language translations
	 * @return array|null The created placeholder data if successful, null on failure
	 */
	public function createCustomPlaceholder(array $placeholderData): ?array
	{
		if (!isset($placeholderData['name'], $placeholderData['value'])) {
			$this->logger->error('Missing required fields: name and value are required.');
			return null;
		}

		return $this->sendRequest('/api/custom-placeholders', HTTPMethod::POST, [], $placeholderData);
	}

	/**
	 * Updates an existing custom placeholder.
	 *
	 * @param int $placeholderId The unique identifier of the placeholder to update
	 * @param array $placeholderData Updated placeholder data (same structure as createCustomPlaceholder)
	 * @return array|null The updated placeholder data if successful, null on failure
	 */
	public function updateCustomPlaceholder(int $placeholderId, array $placeholderData): ?array
	{
		if ($placeholderId <= 0) {
			$this->logger->error('Invalid placeholder ID. Must be a positive integer.');
			return null;
		}

		return $this->sendRequest('/api/custom-placeholders/' . $placeholderId, HTTPMethod::PUT, [], $placeholderData);
	}

	/**
	 * Deletes a custom placeholder.
	 *
	 * @param int $placeholderId The unique identifier of the placeholder to delete
	 * @return bool True if the placeholder was successfully deleted, false on failure
	 */
	public function deleteCustomPlaceholder(int $placeholderId): bool
	{
		if ($placeholderId <= 0) {
			$this->logger->error('Invalid placeholder ID. Must be a positive integer.');
			return false;
		}

		$result = $this->sendRequest('/api/custom-placeholders/' . $placeholderId, HTTPMethod::DELETE);
		return $result !== null;
	}

	// ========================================
	// Online Check-In Methods
	// ========================================

	/**
	 * Retrieves online check-in status for reservations.
	 *
	 * @param array $filters Optional filters for online check-in data. Supported keys:
	 *                       - 'reservationId' (int): Filter by specific reservation ID
	 * @link https://docs.smoobu.com/ Online check-in documentation
	 * @return array|null Array of check-in data if successful, null on failure
	 */
	public function getOnlineCheckIn(array $filters = []): ?array
	{
		return $this->sendRequest('/api/online-check-in', HTTPMethod::GET, $filters);
	}

	/**
	 * Initiates online check-in process for a guest.
	 *
	 * @param array $checkInData Check-in data. Required keys:
	 *                           - 'reservationId' (int): The reservation ID for check-in
	 *                           Optional keys:
	 *                           - 'sendEmail' (bool): Send check-in email to guest
	 * @return array|null The check-in data if successful, null on failure
	 */
	public function createOnlineCheckIn(array $checkInData): ?array
	{
		if (!isset($checkInData['reservationId'])) {
			$this->logger->error('Missing required field: reservationId is required.');
			return null;
		}

		return $this->sendRequest('/api/online-check-in', HTTPMethod::POST, [], $checkInData);
	}

	// ========================================
	// Helper Methods
	// ========================================

	/**
	 * Executes an HTTP request to the Smoobu API, signed with HMAC-SHA256 authentication.
	 * This method handles the common request logic for all API calls, including signing, error handling, and rate limit awareness.
	 *
	 * @param string $endpoint The API endpoint path (e.g., '/api/apartments')
	 * @param HTTPMethod $httpMethod The HTTP method to use (GET, POST, PUT, DELETE)
	 * @param array $queryParams Optional query parameters for GET/DELETE requests
	 * @param array $bodyData Optional request body data for POST/PUT requests
	 * @return array|null The decoded JSON response as an associative array if successful, null on failure
	 */
	private function sendRequest(string $endpoint, HTTPMethod $httpMethod, array $queryParams = [], array $bodyData = []): ?array
	{
		if (empty($this->apiKey) || empty($this->apiSecret)) {
			$this->logger->error('Smoobu API key/secret is not configured. Please set them using setApiKey() and setApiSecret() methods.');
			return null;
		}

		// Query string is built once and reused both for signing and for the actual request, so the signed value always matches what is sent
		$sortedQueryString = URL::buildSortedQueryString($queryParams);
		$timestamp = gmdate('Y-m-d\TH:i:s\Z');
		$nonce = UUID::generate();
		$bodyJson = !empty($bodyData) ? json_encode($bodyData) : '';
		$bodyHash = hash('sha256', $bodyJson);

		$canonicalString = implode("\n", [
			$httpMethod->value,
			$endpoint,
			$sortedQueryString,
			$timestamp,
			$nonce,
			$bodyHash,
			$this->apiKey,
		]);
		$signature = base64_encode(hash_hmac('sha256', $canonicalString, $this->apiSecret, true));

		$url = self::API_URL . $endpoint;
		if ('' !== $sortedQueryString) {
			$url .= '?' . $sortedQueryString;
		}

		// Set custom headers for HMAC authentication
		$headers = [
			'X-API-Key' => $this->apiKey,
			'X-Timestamp' => $timestamp,
			'X-Nonce' => $nonce,
			'X-Signature' => $signature,
			'Accept' => 'application/json',
		];

		// Execute the request (query params are already embedded in the signed URL for GET/DELETE)
		$result = $this->requestExecutor->execute(
			$httpMethod,
			$url,
			in_array($httpMethod, [HTTPMethod::GET, HTTPMethod::DELETE], true) ? [] : $bodyData,
			headers: $headers,
			jsonBody: true,
			decodeJson: true
		);

		// Log rate limit information if available in response headers
		// Note: In production, you would extract these from response headers
		// X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Retry-After

		return $result;
	}
}
