<?php

namespace Osimatic\Network;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

readonly class HTTPRequestExecutor
{
	public function __construct(
		private ClientInterface $httpClient = new HTTPClient(),
		private LoggerInterface $logger = new NullLogger(),
	) {}

	/**
	 * Executes an HTTP request and returns the response (raw or JSON-decoded)
	 * @param HTTPMethod $method HTTP method to use
	 * @param string $url target URL
	 * @param array $queryData For GET/DELETE: query parameters added to URL. For POST/PUT/PATCH: data sent in body (form-urlencoded by default, JSON if $jsonBody is true)
	 * @param array $headers HTTP headers
	 * @param bool $jsonBody If true, send POST/PUT/PATCH data as JSON body instead of form-urlencoded
	 * @return ResponseInterface|null the HTTP response, null if request failed
	 */
	public function send(HTTPMethod $method, string $url, array $queryData = [], array $headers = [], bool $jsonBody = false, array $options = []): ?ResponseInterface
	{
		$body = null;

		// Handle data placement based on HTTP method
		if (!empty($queryData)) {
			if ($method === HTTPMethod::GET || $method === HTTPMethod::DELETE) {
				// For GET and DELETE: data goes in URL query parameters
				$url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($queryData);
			}
			// For POST, PUT, PATCH: data goes in body
			elseif ($jsonBody) {
				// Send as JSON
				try {
					$body = json_encode($queryData, JSON_THROW_ON_ERROR);
				} catch (\JsonException $e) {
					$this->logger->error('Error during JSON encoding. Error: '.$e->getMessage());
				}
				$headers['Content-Type'] = 'application/json';
			}
			else {
				// Send as form-urlencoded (default)
				$body = http_build_query($queryData);
				$headers['Content-Type'] = 'application/x-www-form-urlencoded';
			}
		}

		try {
			// Send request using PSR-18 client
			return $this->httpClient->sendRequest(new Request($method->value, $url, $headers, $body));
		}
		catch (ClientExceptionInterface $e) {
			$this->logger->error('HTTP request failed: ' . $e->getMessage());
		}
		return null;
	}

	/**
	 * Executes an HTTP request and returns the response (raw or JSON-decoded)
	 * @param HTTPMethod $method HTTP method to use
	 * @param string $url target URL
	 * @param array $queryData For GET/DELETE: query parameters added to URL. For POST/PUT/PATCH: data sent in body (form-urlencoded by default, JSON if $jsonBody is true)
	 * @param array $headers HTTP headers
	 * @param bool $jsonBody If true, send POST/PUT/PATCH data as JSON body instead of form-urlencoded
	 * @param bool $decodeJson If true, return JSON-decoded response; if false, return raw response body (default: false)
	 * @return mixed|string|null Raw response body (string), JSON-decoded response (mixed), or null if request failed
	 */
	public function execute(HTTPMethod $method, string $url, array $queryData = [], array $headers = [], bool $jsonBody = false, bool $decodeJson = false): mixed
	{
		if (null === ($response = $this->send($method, $url, $queryData, $headers, $jsonBody))) {
			return null;
		}

		$responseBody = (string) $response->getBody();

		// Decode JSON if requested
		if ($decodeJson) {
			try {
				return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
			}
			catch (\Exception $e) {
				$this->logger->error('Error during JSON decoding. Error: '.$e->getMessage());
				return null;
			}
		}

		return $responseBody;
	}
}