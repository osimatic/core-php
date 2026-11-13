<?php

namespace Tests\Network;

use Osimatic\Network\OriginValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class OriginValidatorTest extends TestCase
{
	/**
	 * Builds a request, the headers being given by their HTTP name (e.g. "Origin", "Sec-Fetch-Site").
	 */
	private static function createRequest(string $method, array $headers = [], string $uri = 'https://example.com/api/items'): Request
	{
		$server = [];
		foreach ($headers as $name => $value) {
			$server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
		}
		return Request::create($uri, $method, [], [], [], $server);
	}

	// ========================================
	// Public Methods Tests
	// ========================================

	public function testIsTrustedRequest(): void
	{
		// Safe methods are always trusted, whatever the headers
		foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
			self::assertTrue(OriginValidator::isTrustedRequest(self::createRequest($method)));
			self::assertTrue(OriginValidator::isTrustedRequest(self::createRequest($method, ['Origin' => 'https://evil.com'])));
		}

		// State-changing methods without any Origin or Sec-Fetch-Site header are refused
		foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'TRACE'] as $method) {
			self::assertFalse(OriginValidator::isTrustedRequest(self::createRequest($method)), $method);
		}

		// Same origin is trusted for every state-changing method
		foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
			self::assertTrue(OriginValidator::isTrustedRequest(self::createRequest($method, ['Origin' => 'https://example.com'])), $method);
		}

		// Other origins: other host, other scheme, other port, subdomain, "null" origin, empty and malformed values
		foreach (['https://evil.com', 'http://example.com', 'https://example.com:8443', 'https://sub.example.com', 'https://example.com.evil.com', 'null', '/', ' '] as $origin) {
			self::assertFalse(OriginValidator::isTrustedRequest(self::createRequest('POST', ['Origin' => $origin])), $origin);
		}
		self::assertFalse(OriginValidator::isTrustedRequest(self::createRequest('POST', ['Origin' => ''])));

		// Origin comparison ignores case and a trailing slash
		self::assertTrue(OriginValidator::isTrustedRequest(self::createRequest('POST', ['Origin' => 'HTTPS://Example.COM'])));
		self::assertTrue(OriginValidator::isTrustedRequest(self::createRequest('POST', ['Origin' => 'https://example.com/'])));

		// Non standard port, which the Origin header carries
		$uri = 'https://example.com:8443/api/items';
		self::assertTrue(OriginValidator::isTrustedRequest(self::createRequest('POST', ['Origin' => 'https://example.com:8443'], $uri)));
		self::assertFalse(OriginValidator::isTrustedRequest(self::createRequest('POST', ['Origin' => 'https://example.com'], $uri)));

		// Extra origins
		$request = self::createRequest('POST', ['Origin' => 'http://localhost:3000']);
		self::assertFalse(OriginValidator::isTrustedRequest($request));
		self::assertFalse(OriginValidator::isTrustedRequest($request, []));
		self::assertFalse(OriginValidator::isTrustedRequest($request, ['http://localhost:3001', 'https://other.com']));
		self::assertTrue(OriginValidator::isTrustedRequest($request, ['https://other.com', 'http://localhost:3000']));
		self::assertTrue(OriginValidator::isTrustedRequest($request, ['HTTP://Localhost:3000/']));
		self::assertTrue(OriginValidator::isTrustedRequest(self::createRequest('POST', ['Origin' => 'https://example.com']), ['http://localhost:3000']));

		// Empty extra origins never match an empty or malformed Origin
		self::assertFalse(OriginValidator::isTrustedRequest(self::createRequest('POST', ['Origin' => '/']), ['', '/']));
		self::assertFalse(OriginValidator::isTrustedRequest(self::createRequest('POST', ['Origin' => '']), ['']));

		// Without Origin header, the Sec-Fetch-Site header is used
		foreach (['same-origin', 'none', 'SAME-ORIGIN'] as $fetchSite) {
			self::assertTrue(OriginValidator::isTrustedRequest(self::createRequest('POST', ['Sec-Fetch-Site' => $fetchSite])), $fetchSite);
		}
		foreach (['same-site', 'cross-site', 'unknown', ''] as $fetchSite) {
			self::assertFalse(OriginValidator::isTrustedRequest(self::createRequest('POST', ['Sec-Fetch-Site' => $fetchSite])), $fetchSite);
		}

		// The Origin header prevails over Sec-Fetch-Site when both are present
		self::assertFalse(OriginValidator::isTrustedRequest(self::createRequest('POST', ['Origin' => 'https://evil.com', 'Sec-Fetch-Site' => 'same-origin'])));
		self::assertTrue(OriginValidator::isTrustedRequest(self::createRequest('POST', ['Origin' => 'https://example.com', 'Sec-Fetch-Site' => 'cross-site'])));

		// A method override cannot disguise a state-changing request as a safe one
		self::assertFalse(OriginValidator::isTrustedRequest(self::createRequest('POST', ['X-HTTP-Method-Override' => 'GET'])));
		self::assertFalse(OriginValidator::isTrustedRequest(self::createRequest('POST', ['X-HTTP-Method-Override' => 'GET', 'Origin' => 'https://evil.com'])));
		self::assertTrue(OriginValidator::isTrustedRequest(self::createRequest('POST', ['X-HTTP-Method-Override' => 'GET', 'Origin' => 'https://example.com'])));

		// A real safe method stays trusted even with an override header
		self::assertTrue(OriginValidator::isTrustedRequest(self::createRequest('GET', ['X-HTTP-Method-Override' => 'POST'])));
	}
}
