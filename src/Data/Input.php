<?php

namespace Osimatic\Data;

use Osimatic\Network\HTTPMethod;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Unified input handler for retrieving values from both HTTP and CLI contexts.
 *
 * This class provides a consistent interface for accessing input data regardless of whether
 * the code is running in an HTTP context (Symfony Request) or CLI context (Symfony Console Input).
 * It offers type-safe methods for retrieving boolean, integer, float, string, and array values
 * with proper default handling and validation.
 *
 * Key features:
 * - Unified API for HTTP and CLI contexts
 * - Type-safe value retrieval with validation
 * - Default value support for all types
 * - Integration with FormService for robust parsing
 * - Consistent behavior across contexts
 *
 * Usage examples:
 * ```php
 * // HTTP context
 * $request = Request::createFromGlobals();
 * $userId = Input::getInt($request, 'user_id', 0);
 * $isActive = Input::getBool($request, 'active', false);
 *
 * // CLI context
 * $input = new ArgvInput();
 * $limit = Input::getInt($input, 'limit', 10);
 * $verbose = Input::getBool($input, 'verbose', false);
 * ```
 */
class Input
{
	/**
	 * Retrieves a raw value from HTTP Request or CLI Input based on the context.
	 *
	 * For HTTP requests (Request), retrieves the value from query parameters or request body based on the HTTP method:
	 * - If $httpMethod is GET: retrieves from query parameters only
	 * - If $httpMethod is POST/PUT/PATCH/DELETE: retrieves from request body only
	 * - If $httpMethod is null: checks query parameters first, then request body
	 *
	 * For CLI input (InputInterface), prioritizes options over arguments:
	 * - First checks if an option with the key exists and has a non-empty value
	 * - Falls back to argument if option is not found or is empty
	 *
	 * Example:
	 * ```php
	 * // HTTP: GET /api/users?id=123
	 * $id = Input::get($request, 'id'); // Returns: '123' (from query)
	 * $id = Input::get($request, 'id', HTTPMethod::GET); // Returns: '123' (from query only)
	 *
	 * // HTTP: POST /api/users with body: {name: 'John'}
	 * $name = Input::get($request, 'name', HTTPMethod::POST); // Returns: 'John' (from request body)
	 *
	 * // CLI: php bin/console command --name=John argument_value
	 * $name = Input::get($input, 'name'); // Returns: 'John' (from option)
	 * $arg = Input::get($input, 'arg_name'); // Returns: 'argument_value' (from argument)
	 * ```
	 *
	 * @param Request|InputInterface|InputBag $input The HTTP Request or CLI InputInterface
	 * @param string $key The parameter name to retrieve
	 * @param HTTPMethod|null $httpMethod HTTP method to determine parameter source (query vs request body)
	 * @return mixed|null The raw value if found, null otherwise
	 */
	public static function get(Request|InputInterface|InputBag $input, string $key, ?HTTPMethod $httpMethod = null): mixed
	{
		if ($input instanceof InputBag) {
			return $input->all()[$key] ?? null;
		}

		if ($input instanceof Request) {
			// If HTTP method is specified, use appropriate parameter bag
			if ($httpMethod !== null) {
				return match ($httpMethod) {
					HTTPMethod::GET => $input->query->all()[$key] ?? null,
					HTTPMethod::POST, HTTPMethod::PUT, HTTPMethod::PATCH, HTTPMethod::DELETE => $input->request->all()[$key] ?? null,
				};
			}

			// If no HTTP method specified, check query first, then request
			// Use all() to handle both scalar and array values
			return $input->query->all()[$key] ?? $input->request->all()[$key] ?? null;
		}

		// CLI: option takes precedence over argument
		if ($input->hasOption($key)) {
			$value = $input->getOption($key);
			if ($value !== null && $value !== '') {
				return $value;
			}
		}
		if ($input->hasArgument($key)) {
			return $input->getArgument($key);
		}

		return null;
	}

	/**
	 * Retrieves a boolean value from HTTP or CLI input with proper parsing.
	 *
	 * Accepts various string representations:
	 * - true: 'true', '1', 'yes', 'on' (case-insensitive)
	 * - false: 'false', '0', 'no', 'off', '' (case-insensitive)
	 *
	 * Example:
	 * ```php
	 * // HTTP: GET /api?debug=true
	 * $debug = Input::getBool($request, 'debug', false); // Returns: true
	 *
	 * // CLI: php bin/console command --verbose
	 * $verbose = Input::getBool($input, 'verbose', false); // Returns: true
	 *
	 * // Missing parameter
	 * $missing = Input::getBool($request, 'nonexistent', true); // Returns: true (default)
	 * ```
	 *
	 * @param Request|InputInterface|InputBag $input The HTTP Request or CLI InputInterface
	 * @param string $key The parameter name to retrieve
	 * @param bool $default The default value if parameter is missing or invalid (default: false)
	 * @return bool The parsed boolean value or default
	 */
	public static function getBool(Request|InputInterface|InputBag $input, string $key, bool $default=false): bool
	{
		$value = self::get($input, $key);
		$result = FormService::parseBoolean($value);
		return $result ?? $default;
	}

	/**
	 * Retrieves an integer value from HTTP or CLI input with optional range validation.
	 *
	 * Parses numeric strings into integers and validates against min/max constraints.
	 * Returns the default value if:
	 * - The parameter is missing
	 * - The value is not numeric
	 * - The value is outside the specified range
	 *
	 * Example:
	 * ```php
	 * // HTTP: GET /api?page=5
	 * $page = Input::getInt($request, 'page', 1); // Returns: 5
	 *
	 * // With range validation
	 * $limit = Input::getInt($request, 'limit', 10, 1, 100); // Returns: 10-100 or default
	 *
	 * // Invalid value
	 * $invalid = Input::getInt($request, 'invalid', 0); // Returns: 0 (default)
	 * ```
	 *
	 * @param Request|InputInterface|InputBag $input The HTTP Request or CLI InputInterface
	 * @param string $key The parameter name to retrieve
	 * @param int $default The default value if parameter is missing or invalid (default: 0)
	 * @param int|null $min Minimum allowed value (inclusive, default: null for no minimum)
	 * @param int|null $max Maximum allowed value (inclusive, default: null for no maximum)
	 * @return int The parsed integer value (within range) or default
	 */
	public static function getInt(Request|InputInterface|InputBag $input, string $key, int $default=0, ?int $min=null, ?int $max=null): int
	{
		$value = self::get($input, $key);
		$result = FormService::parseInteger($value, $min, $max);
		return $result ?? $default;
	}

	/**
	 * Retrieves a float value from HTTP or CLI input with optional range validation.
	 *
	 * Parses numeric strings into floats and validates against min/max constraints.
	 * Returns the default value if:
	 * - The parameter is missing
	 * - The value is not numeric
	 * - The value is outside the specified range
	 *
	 * Example:
	 * ```php
	 * // HTTP: GET /api?price=19.99
	 * $price = Input::getFloat($request, 'price', 0.0); // Returns: 19.99
	 *
	 * // With range validation
	 * $discount = Input::getFloat($request, 'discount', 0.0, 0.0, 100.0); // Returns: 0-100 or default
	 * ```
	 *
	 * @param Request|InputInterface|InputBag $input The HTTP Request or CLI InputInterface
	 * @param string $key The parameter name to retrieve
	 * @param float $default The default value if parameter is missing or invalid (default: 0.0)
	 * @param float|null $min Minimum allowed value (inclusive, default: null for no minimum)
	 * @param float|null $max Maximum allowed value (inclusive, default: null for no maximum)
	 * @return float The parsed float value (within range) or default
	 */
	public static function getFloat(Request|InputInterface|InputBag $input, string $key, float $default=0.0, ?float $min=null, ?float $max=null): float
	{
		$value = self::get($input, $key);
		$result = FormService::parseFloat($value, $min, $max);
		return $result ?? $default;
	}

	/**
	 * Retrieves a string value from HTTP or CLI input with optional trimming.
	 *
	 * By default, trims whitespace from the retrieved string.
	 * Returns the default value if the parameter is missing.
	 *
	 * Example:
	 * ```php
	 * // HTTP: GET /api?name=  John
	 * $name = Input::getString($request, 'name'); // Returns: 'John' (trimmed)
	 *
	 * // Without trimming
	 * $raw = Input::getString($request, 'name', '', false); // Returns: '  John  '
	 *
	 * // With default
	 * $missing = Input::getString($request, 'nonexistent', 'Guest'); // Returns: 'Guest'
	 * ```
	 *
	 * @param Request|InputInterface|InputBag $input The HTTP Request or CLI InputInterface
	 * @param string $key The parameter name to retrieve
	 * @param string $default The default value if parameter is missing (default: '')
	 * @param bool $trim Whether to trim whitespace from the value (default: true)
	 * @return string The string value (optionally trimmed) or default
	 */
	public static function getString(Request|InputInterface|InputBag $input, string $key, string $default='', bool $trim=true): string
	{
		$value = self::get($input, $key);

		if ($value === null) {
			return $default;
		}

		$stringValue = (string) $value;

		if ($trim) {
			return FormService::trim($stringValue) ?? '';
		}

		return $stringValue;
	}

	/**
	 * Retrieves an array value from HTTP or CLI input with optional parsing.
	 *
	 * Handles various input formats:
	 * - Array values are returned as-is
	 * - String values can be split by a separator (e.g., comma-separated)
	 * - Empty values can be filtered out
	 * - Non-array values are converted to single-element arrays
	 *
	 * Example:
	 * ```php
	 * // HTTP: GET /api?ids[]=1&ids[]=2&ids[]=3
	 * $ids = Input::getArray($request, 'ids'); // Returns: [1, 2, 3]
	 *
	 * // Comma-separated string
	 * // GET /api?tags=php,symfony,doctrine
	 * $tags = Input::getArray($request, 'tags', [], ','); // Returns: ['php', 'symfony', 'doctrine']
	 *
	 * // With empty filtering disabled
	 * $values = Input::getArray($request, 'values', [], null, false); // Keeps empty values
	 * ```
	 *
	 * @param Request|InputInterface|InputBag $input The HTTP Request or CLI InputInterface
	 * @param string $key The parameter name to retrieve
	 * @param array $default The default value if parameter is missing (default: [])
	 * @param string|null $separator If provided and input is a string, split by this separator (default: null)
	 * @param bool $filterEmpty Whether to filter out empty values (default: true)
	 * @return array The parsed array or default
	 */
	public static function getArray(Request|InputInterface|InputBag $input, string $key, array $default=[], ?string $separator=null, bool $filterEmpty=true): array
	{
		$value = self::get($input, $key);

		if ($value === null) {
			return $default;
		}

		return FormService::parseArray($value, $filterEmpty, $separator);
	}

	/**
	 * Checks if a parameter exists in HTTP or CLI input.
	 *
	 * For HTTP requests, checks if the parameter exists in query or request data.
	 * For CLI input, checks if the parameter exists as an option or argument.
	 *
	 * Example:
	 * ```php
	 * if (Input::has($request, 'user_id')) {
	 *     $userId = Input::getInt($request, 'user_id');
	 * }
	 * ```
	 *
	 * @param Request|InputInterface|InputBag $input The HTTP Request or CLI InputInterface
	 * @param string $key The parameter name to check
	 * @return bool True if the parameter exists, false otherwise
	 */
	public static function has(Request|InputInterface|InputBag $input, string $key): bool
	{
		if ($input instanceof InputBag) {
			return $input->has($key);
		}

		if ($input instanceof Request) {
			return $input->query->has($key) || $input->request->has($key);
		}

		return $input->hasOption($key) || $input->hasArgument($key);
	}
}