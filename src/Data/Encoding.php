<?php

namespace Osimatic\Data;

/**
 * Utility class for data encoding schemes.
 */
class Encoding
{
	// ========== Base64URL ==========

	/**
	 * Encodes data to Base64URL (RFC 4648, section 5).
	 * Unlike standard Base64, Base64URL is safe to use in URLs and file paths,
	 * which makes it the encoding of choice for tokens such as JWT segments.
	 * @param string $data The data to encode
	 * @return string The Base64URL-encoded string
	 * @link https://datatracker.ietf.org/doc/html/rfc4648#section-5 RFC 4648, section 5
	 */
	public static function encodeBase64URL(string $data): string
	{
		// Convert Base64 to Base64URL by replacing "+" with "-" and "/" with "_"
		$url = strtr(base64_encode($data), '+/', '-_');

		// Remove padding character from the end of line and return the Base64URL result
		return rtrim($url, '=');
	}

	/**
	 * Decodes data from Base64URL.
	 * If the strict parameter is set to true, the function will return false
	 * if the input contains a character from outside the Base64 alphabet. Otherwise,
	 * invalid characters will be silently discarded.
	 * @param string $data The Base64URL-encoded string to decode
	 * @param bool $strict Whether to reject invalid characters instead of discarding them
	 * @return string|false The decoded data, or false on failure when $strict is true
	 */
	public static function decodeBase64URL(string $data, bool $strict = false): string|false
	{
		// Convert Base64URL to Base64 by replacing "-" with "+" and "_" with "/"
		$b64 = strtr($data, '-_', '+/');

		// Decode Base64 string and return the original data
		return base64_decode($b64, $strict);
	}
}