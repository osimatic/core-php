<?php

namespace Osimatic\Network;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class HTTPClient
 * HTTP client wrapper using Guzzle for making HTTP requests.
 * Implements PSR-18 HTTP Client interface for interoperability.
 */
class HTTPClient implements ClientInterface
{
	/** Cached Guzzle client instance for reuse across multiple requests */
	private Client $guzzleClient;

	/**
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 * @param array $defaultOptions Default Guzzle options applied to all requests (timeout, base_uri, etc.)
	 */
	public function __construct(
		private LoggerInterface $logger = new NullLogger(),
		array $defaultOptions = [],
	) {
		$this->guzzleClient = new Client($defaultOptions);
	}

	/**
	 * Sets the logger for error and debugging information.
	 * @param LoggerInterface $logger The PSR-3 logger instance
	 * @return self Returns this instance for method chaining
	 */
	public function setLogger(LoggerInterface $logger): self
	{
		$this->logger = $logger;

		return $this;
	}

	/**
	 * Sets default options for all requests.
	 * Common options: timeout (float), connect_timeout (float), base_uri (string), verify (bool)
	 * Note: Recreates the Guzzle client to apply new options
	 * @param array $defaultOptions Default Guzzle options
	 * @return self Returns this instance for method chaining
	 */
	public function setDefaultOptions(array $defaultOptions): self
	{
		// Recreate client with new options
		$this->guzzleClient = new Client($defaultOptions);

		return $this;
	}

	/**
	 * Sends a PSR-7 request and returns a PSR-7 response (PSR-18 implementation).
	 * @param RequestInterface $request The PSR-7 request to send
	 * @return ResponseInterface The PSR-7 response
	 * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request
	 */
	public function sendRequest(RequestInterface $request): ResponseInterface
	{
		try {
			// Guzzle Client implements PSR-18 ClientInterface, so we can delegate directly
			return $this->guzzleClient->sendRequest($request);
		}
		catch (GuzzleException $e) {
			$this->logger->error('Error during PSR-18 request to '.$request->getUri().'. Error message: '.$e->getMessage());
			// Re-throw the exception as Guzzle exceptions already implement PSR-18 ClientExceptionInterface
			throw $e;
		}
	}

	/**
	 * Sends an HTTP request and returns the PSR-7 response object
	 * @param HTTPMethod $method HTTP method to use
	 * @param string $url target URL
	 * @param array $queryData query parameters (for GET) or body data (for other methods)
	 * @param array $headers HTTP headers
	 * @param bool $jsonBody if true, send body as JSON; if false, send as form data (for non-GET requests)
	 * @param array $options additional Guzzle options (merged with default options from constructor)
	 * @return ResponseInterface|null the HTTP response, null if request failed
	 */
	public function send(HTTPMethod $method, string $url, array $queryData = [], array $headers = [], bool $jsonBody = false, array $options = []): ?ResponseInterface
	{
		try {
			// Set http_errors to false to return responses instead of throwing exceptions for 4xx/5xx
			$options['http_errors'] = $options['http_errors'] ?? false;

			// Merge headers properly
			if (!empty($headers)) {
				$options['headers'] = array_merge($options['headers'] ?? [], $headers);
			}

			if (!empty($queryData)) {
				if (HTTPMethod::GET === $method) {
					$options['query'] = array_merge($options['query'] ?? [], $queryData);
				}
				else {
					if ($jsonBody) {
						$options['json'] = $queryData;
					} else {
						$options['form_params'] = $queryData;
					}
				}
			}

			return $this->guzzleClient->request($method->value, $url, $options);
		}
		catch (\Exception | GuzzleException $e) {
			$this->logger->error('Error during '.$method->value.' request to URL '.$url.'. Error message: '.$e->getMessage());
		}
		return null;
	}

	/**
	 * Executes an HTTP request and returns the response body (raw or JSON-decoded)
	 * @param HTTPMethod $method HTTP method to use
	 * @param string $url target URL
	 * @param array $queryData query parameters (for GET) or body data (for other methods)
	 * @param array $headers HTTP headers
	 * @param bool $jsonBody if true, send body as JSON; if false, send as form data (for non-GET requests)
	 * @param bool $decodeJson If true, return JSON-decoded response; if false, return raw response body (default: false)
	 * @param array $options additional Guzzle options (merged with default options from constructor)
	 * @return mixed|string|null Raw response body (string), JSON-decoded response (mixed), or null if request failed
	 */
	public function execute(HTTPMethod $method, string $url, array $queryData = [], array $headers = [], bool $jsonBody = false, bool $decodeJson = false, array $options = []): mixed
	{
		if (null === ($response = $this->send($method, $url, $queryData, $headers, $jsonBody, $options))) {
			return null;
		}

		$responseBody = (string) $response->getBody();

		// Decode JSON if requested
		if ($decodeJson) {
			try {
				return \GuzzleHttp\Utils::jsonDecode($responseBody, true);
			}
			catch (\Exception $e) {
				$this->logger->error('Error during JSON decoding. Error: '.$e->getMessage());
				return null;
			}
		}

		return $responseBody;
	}

	// ========== DEPRECATED METHODS ==========
	// Backward compatibility. Will be removed in a future major version. Please update your code to use the new method names.

	/**
	 * @deprecated Use send() instead
	 */
	public function request(HTTPMethod $method, string $url, array $queryData = [], array $headers = [], bool $jsonBody = false, array $options = []): ?ResponseInterface
	{
		return $this->send($method, $url, $queryData, $headers, $jsonBody, $options);
	}

	/**
	 * @deprecated Use execute() with decodeJson: true instead
	 */
	public function jsonRequest(HTTPMethod $method, string $url, array $queryData = [], array $headers = [], bool $jsonBody = false, array $options = []): mixed
	{
		return $this->execute($method, $url, $queryData, $headers, $jsonBody, true, $options);
	}

	/**
	 * @deprecated Use execute() with decodeJson: false instead
	 */
	public function stringRequest(HTTPMethod $method, string $url, array $queryData = [], array $headers = [], bool $jsonBody = false, array $options = []): ?string
	{
		return $this->execute($method, $url, $queryData, $headers, $jsonBody, false, $options);
	}

}