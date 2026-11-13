<?php

namespace Osimatic\Network;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Minimal router for small JSON APIs: maps an HTTP method and an exact path to a handler.
 * A handler is any callable receiving the Request and returning a Response. Unknown paths get a 404 JSON response, known paths called with another method get a 405 JSON response with an "Allow" header.
 * Path parameters are not supported; paths are compared after removing the optional base path and the trailing slash.
 * Cross-site request forgery protection can be switched on with requireTrustedOrigin(): state-changing requests that do not come from the application get a 403 JSON response before reaching their handler.
 *
 * @link https://symfony.com/doc/current/components/http_foundation.html Symfony HttpFoundation
 */
class ApiRouter
{
	// ========================================
	// Properties
	// ========================================

	/** @var array<string, array<string, callable>> Handlers indexed by path then by HTTP method */
	private array $routes = [];

	/** Whether state-changing requests must come from a trusted origin (see requireTrustedOrigin()) */
	private bool $originCheckEnabled = false;

	/** @var string[] Origins accepted in addition to the origin of the application when the origin check is enabled */
	private array $extraOrigins = [];

	// ========================================
	// Constructor & Configuration
	// ========================================

	/**
	 * @param string $basePath Prefix common to all routes, removed from the request path before matching (e.g. "/api")
	 */
	public function __construct(private readonly string $basePath = '') {}

	/**
	 * Switches on the protection against cross-site request forgery: a request using a state-changing method (POST, PUT, PATCH, DELETE) is only handled if it comes from the origin of the application or from one of the extra origins, otherwise it gets a 403 JSON response.
	 * Safe methods (GET, HEAD, OPTIONS) are never checked, so their handlers must not change data.
	 *
	 * @param string[] $extraOrigins Other allowed origins, in the form "https://example.com" without path (e.g. a local development host)
	 * @return self Returns this instance for method chaining
	 * @see OriginValidator::isTrustedRequest()
	 */
	public function requireTrustedOrigin(array $extraOrigins = []): self
	{
		$this->originCheckEnabled = true;
		$this->extraOrigins = $extraOrigins;
		return $this;
	}

	// ========================================
	// Route Registration Methods
	// ========================================

	/**
	 * Registers a handler for an HTTP method and a path. Registering the same method and path again replaces the previous handler.
	 *
	 * @param HTTPMethod $method HTTP method of the route
	 * @param string $path Path relative to the base path (e.g. "/auth/logout")
	 * @param callable $handler Callable receiving the Request and returning a Response
	 * @return self Returns this instance for method chaining
	 */
	public function add(HTTPMethod $method, string $path, callable $handler): self
	{
		$this->routes[self::normalizePath($path)][$method->value] = $handler;
		return $this;
	}

	// ========================================
	// Dispatch Methods
	// ========================================

	/**
	 * Finds the handler matching the request and returns its response.
	 *
	 * @param Request $request The incoming request
	 * @return Response The response of the handler, or a 404 / 405 JSON response if no handler matches, or a 403 JSON response if the origin check is enabled and the request does not come from a trusted origin
	 */
	public function dispatch(Request $request): Response
	{
		$path = $this->getRelativePath($request);
		if (null === $path || !isset($this->routes[$path])) {
			return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
		}

		$handlers = $this->routes[$path];
		$handler = $handlers[strtoupper($request->getMethod())] ?? null;
		if (null === $handler) {
			return new JsonResponse(['error' => 'method_not_allowed'], Response::HTTP_METHOD_NOT_ALLOWED, ['Allow' => implode(', ', array_keys($handlers))]);
		}

		if ($this->originCheckEnabled && !OriginValidator::isTrustedRequest($request, $this->extraOrigins)) {
			return new JsonResponse(['error' => 'forbidden_origin'], Response::HTTP_FORBIDDEN);
		}

		return $handler($request);
	}

	// ========================================
	// Helper Methods
	// ========================================

	/**
	 * Returns the request path without the base path, normalized.
	 * The base path only matches whole path segments (e.g. "/api" matches "/api/ping" but not "/apiping").
	 *
	 * @return string|null The relative path, or null if the request path is not under the base path
	 */
	private function getRelativePath(Request $request): ?string
	{
		$path = self::normalizePath($request->getPathInfo());
		$basePath = self::normalizePath($this->basePath);
		if ('/' === $basePath) {
			return $path;
		}
		if ($path === $basePath || str_starts_with($path, $basePath . '/')) {
			return self::normalizePath(substr($path, strlen($basePath)));
		}
		return null;
	}

	/**
	 * Normalizes a path: leading slash, no trailing slash (except for the root path).
	 */
	private static function normalizePath(string $path): string
	{
		return '/' . trim($path, '/');
	}
}