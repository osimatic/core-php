<?php

namespace Tests\Network;

use Osimatic\Network\ApiRouter;
use Osimatic\Network\HTTPMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiRouterTest extends TestCase
{
	// ========================================
	// Constructor & Configuration Tests
	// ========================================

	public function testConstructor(): void
	{
		// Without base path, routes are matched on the full path
		$router = new ApiRouter();
		$router->add(HTTPMethod::GET, '/ping', fn() => new Response('pong'));
		self::assertSame('pong', $router->dispatch(Request::create('/ping'))->getContent());
		self::assertSame(404, $router->dispatch(Request::create('/api/ping'))->getStatusCode());

		// With base path, it is removed before matching
		$router = new ApiRouter('/api');
		$router->add(HTTPMethod::GET, '/ping', fn() => new Response('pong'));
		self::assertSame('pong', $router->dispatch(Request::create('/api/ping'))->getContent());
		self::assertSame(404, $router->dispatch(Request::create('/ping'))->getStatusCode());

		// The base path only matches whole path segments
		self::assertSame(404, $router->dispatch(Request::create('/apiping'))->getStatusCode());
	}

	// ========================================
	// Route Registration Methods Tests
	// ========================================

	public function testAdd(): void
	{
		$router = new ApiRouter();

		// Chaining, method case-insensitive, slashes normalized
		self::assertSame($router, $router->add(HTTPMethod::GET, 'items/', fn() => new Response('list')));
		self::assertSame('list', $router->dispatch(Request::create('/items'))->getContent());
		self::assertSame('list', $router->dispatch(Request::create('/items/'))->getContent());

		// Same path, different methods
		$router->add(HTTPMethod::POST, '/items', fn() => new Response('created', 201));
		self::assertSame('created', $router->dispatch(Request::create('/items', 'POST'))->getContent());
		self::assertSame('list', $router->dispatch(Request::create('/items'))->getContent());

		// Registering the same method and path again replaces the handler
		$router->add(HTTPMethod::GET, '/items', fn() => new Response('replaced'));
		self::assertSame('replaced', $router->dispatch(Request::create('/items'))->getContent());
	}

	// ========================================
	// Dispatch Methods Tests
	// ========================================

	public function testDispatch(): void
	{
		$router = new ApiRouter('/api');
		$router->add(HTTPMethod::GET, '/session', fn(Request $request) => new JsonResponse(['path' => $request->getPathInfo()]));
		$router->add(HTTPMethod::POST, '/session', fn() => new Response('', 204));

		// Matching route: the handler receives the request
		$response = $router->dispatch(Request::create('/api/session'));
		self::assertSame(200, $response->getStatusCode());
		self::assertSame(['path' => '/api/session'], json_decode($response->getContent(), true));

		// Unknown path
		$response = $router->dispatch(Request::create('/api/unknown'));
		self::assertSame(404, $response->getStatusCode());
		self::assertSame(['error' => 'not_found'], json_decode($response->getContent(), true));

		// Known path, method not registered
		$response = $router->dispatch(Request::create('/api/session', 'DELETE'));
		self::assertSame(405, $response->getStatusCode());
		self::assertSame('GET, POST', $response->headers->get('Allow'));
		self::assertSame(['error' => 'method_not_allowed'], json_decode($response->getContent(), true));
	}
}