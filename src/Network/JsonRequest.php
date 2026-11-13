<?php

namespace Osimatic\Network;

use Symfony\Component\HttpFoundation\Request;

/**
 * Helper reading the JSON body of an HTTP request, without throwing on missing or malformed content.
 *
 * @link https://www.php.net/manual/en/function.json-decode.php json_decode
 */
final class JsonRequest
{
	// ========================================
	// Public Methods
	// ========================================

	/**
	 * Returns the decoded JSON body of the request.
	 * An empty body, a body that is not valid JSON, or a JSON value that is not an object or an array, all give an empty array.
	 *
	 * @param Request $request The incoming request
	 * @return array<string, mixed> The decoded body, or an empty array
	 */
	public static function getBody(Request $request): array
	{
		try {
			$body = $request->toArray();
		} catch (\JsonException) {
			return [];
		}
		return $body;
	}
}