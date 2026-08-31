<?php

namespace Osimatic\Network;

/**
 * Class URL
 * Provides utilities for URL validation, parsing, and manipulation
 */
class URL
{
	const array VALID_SCHEMES = ['http', 'https', 'ftp', 'ftps', 'mailto', 'tel', 'ssh', 'sftp', 'file'];
	
	// ========== Check ==========

	/**
	 * Checks the syntax of a URL
	 * @param string $url the URL to check
	 * @param bool $checkScheme if true, also validates that the scheme is in the list of valid schemes (default: false)
	 * @return boolean true if the URL is syntactically correct, false otherwise
	 */
	public static function isValid(string $url, bool $checkScheme=false): bool
	{
		if (filter_var($url, FILTER_VALIDATE_URL) === false) {
			return false;
		}

		$parsedUrl = parse_url($url);
		if (false === $parsedUrl || !isset($parsedUrl['host'])) {
			return false;
		}

		// Verify that the scheme is valid
		if ($checkScheme && (!isset($parsedUrl['scheme']) || !in_array(strtolower($parsedUrl['scheme']), self::VALID_SCHEMES, true))) {
			return false;
		}

		return true;
	}

	// ========== Display ==========

	/**
	 * Formats a URL for display by converting all characters to lowercase and optionally removing the protocol
	 * @param string $url the URL to format for display
	 * @param boolean $withProtocol true to keep the protocol in the URL, false to remove it (false by default)
	 * @return string the formatted URL ready to be displayed
	 */
	public static function format(string $url, bool $withProtocol=false): string
	{
		if (!$withProtocol && str_contains($url, '://')) {
			$url = substr($url, strpos($url, '://')+3);
		}
		if (str_ends_with($url, '/')) { // todo: test if contains path (in this case do not remove the last slash)
			$url = substr($url, 0, -1);
		}
		return self::toLowerCase($url);
	}

	/**
	 * Converts only the domain name to lowercase (not the path or arguments)
	 * @param  string $url URL to format
	 * @return string the formatted URL
	 */
	public static function toLowerCase(string $url): string
	{
		// Get the position of the first "/" separating the domain part (http://domain.tld) from the rest of the URL
		preg_match('/\w(\/)\w/', $url, $matches, PREG_OFFSET_CAPTURE);

		if (empty($matches)) {
			// i.e http://mon-site.fr[?arg=unArg]
			if (false === strrpos($url, '?')) {
				return mb_strtolower($url);
			}
			$tabUrl = explode('?', $url, 2);
			return mb_strtolower($tabUrl[0]) . '?' . $tabUrl[1];
		}

		// i.e http://my-site.com/categ1[/categ2?arg=anArg]
		// Reconstruct the URL
		// Match[1][1] contains the position of "/"
		return mb_strtolower(substr($url, 0, $matches[1][1])) . substr($url, $matches[1][1]);
	}

	// ========== Components of URL ==========

	/**
	 * Returns the top-level domain from a URL, optionally with the "." separator
	 * @param string $url the URL from which to retrieve the top-level domain
	 * @param boolean $withPoint true to add the "." separator before the top-level domain, false otherwise (true by default)
	 * @return string the top-level domain from the URL
	 */
	public static function getTld(string $url, bool $withPoint=true): string
	{
		$host = self::getHost($url, false, true);

		return DNS::getTld($host, $withPoint);
	}

	/**
	 * Alias of the URL::getTld() function
	 * @param string $url
	 * @return string
	 */
	public static function getTopLevelDomain(string $url): string
	{
		return self::getTld($url);
	}

	/**
	 * Returns the second-level domain from a URL, optionally with the top-level domain
	 * @param string $url the URL from which to retrieve the second-level domain
	 * @param boolean $withTld true to add the top-level domain from the URL, false otherwise (true by default)
	 * @return string the second-level domain from the URL
	 */
	public static function getSld(string $url, bool $withTld=true): string
	{
		$host = self::getHost($url, false, true);

		return DNS::getSld($host, $withTld);
	}

	/**
	 * Alias of the URL::getSld() function
	 * @param string $url
	 * @param bool $withTld
	 * @return string
	 */
	public static function getSecondLevelDomain(string $url, bool $withTld=true): string
	{
		return self::getSld($url, $withTld);
	}

	/**
	 * Returns the protocol from a URL, optionally with the "://" separator added
	 * @param string $url the URL from which to retrieve the protocol
	 * @param boolean $withSchemeSeparator true to add the "://" separator after the protocol, false otherwise (true by default)
	 * @param boolean $returnEmptyStringIfNoScheme true to return an empty string if the protocol is not present in the URL, false to return the default HTTP protocol if not present (false by default)
	 * @return string the protocol from the URL
	 */
	public static function getScheme(string $url, bool $withSchemeSeparator=true, bool $returnEmptyStringIfNoScheme=false): string
	{
		$tabInfosUrl = parse_url($url);
		$scheme = $tabInfosUrl['scheme'] ?? '';

		if (empty($scheme)) {
			if ($returnEmptyStringIfNoScheme) {
				return '';
			}
			$scheme = 'http';
		}

		if ($withSchemeSeparator && !str_contains($scheme, '://')) {
			$scheme .= '://';
		}
		return $scheme;
	}


	/**
	 * Returns the domain name from a URL, optionally adding or removing the "/" character at the end
	 * @param string $url the URL from which to retrieve the domain name
	 * @param boolean $addSlashAtEnd true to add (if not present) the "/" character at the end of the domain name, false otherwise (true by default)
	 * @param boolean $deleteSlashAtEnd true to remove (if present) the "/" character at the end of the domain name, false otherwise (false by default)
	 * @return string the domain name from the URL (or from the string)
	 */
	public static function getHost(string $url, bool $addSlashAtEnd=true, bool $deleteSlashAtEnd=false): string
	{
		$tabInfosUrl = parse_url($url);
		$host = $tabInfosUrl['host'] ?? '';
		if ($addSlashAtEnd && !str_ends_with($host, '/')) {
			$host .= '/';
		}
		if ($deleteSlashAtEnd && str_ends_with($host, '/')) {
			$host = substr($host, 0, -1);
		}
		return $host;
	}

	/**
	 * Returns the port from a URL
	 * @param string $url the URL from which to retrieve the port
	 * @return string|null the port from the URL (or from the string)
	 */
	public static function getPort(string $url): ?string
	{
		$tabInfosUrl = parse_url($url);
		$port = $tabInfosUrl['port'] ?? '';
		if (empty($port)) {
			return null;
		}
		return $port;
	}

	/**
	 * Returns the path from a URL, optionally adding or removing the "/" character at the beginning
	 * @param string $url the URL from which to retrieve the path
	 * @param boolean $slashAtBeginning true to add a slash at the beginning of the path, false to remove it (true by default)
	 * @return string the path from the URL
	 */
	public static function getPath(string $url, bool $slashAtBeginning=true): string
	{
		$tabInfosUrl = parse_url($url);
		$path = $tabInfosUrl['path'] ?? '';

		if ($slashAtBeginning && !str_starts_with($path, '/')) {
			$path = '/'.$path;
		}
		elseif (!$slashAtBeginning && str_starts_with($path, '/')) {
			$path = substr($path, 1);
		}
		return $path;
	}

	/**
	 * Returns the file name (without its path) from a URL
	 * @param string $url the URL from which to retrieve the file name
	 * @return string the file name (without its path) from the URL
	 */
	public static function getFile(string $url): string
	{
		$path = self::getPath($url, true);
		if (false !== ($lastSlashPos = strrpos($path, '/'))) {
			$fileName = substr($path, $lastSlashPos + 1);
		}
		return $fileName ?? '';
	}


	/**
	 * Returns the GET parameters (as a string or array) from a URL, optionally with the "?" character at the beginning
	 * @param string $url the URL from which to retrieve the string corresponding to the GET parameters
	 * @param boolean $withQueryStringSeparator true to add the "?" separator at the beginning, false otherwise (true by default)
	 * @param boolean $withQueryStringSeparatorIfEmptyQueryString true to add the "?" separator at the beginning even if there are no GET parameters, false otherwise (false by default)
	 * @return string the string corresponding to the GET parameters from the URL or an associative array with the parameter name as key and the parameter value as value
	 */
	public static function getQueryString(string $url, bool $withQueryStringSeparator=false, bool $withQueryStringSeparatorIfEmptyQueryString=false): string
	{
		$tabInfosUrl = parse_url($url);
		$queryString = $tabInfosUrl['query'] ?? '';

		if ($withQueryStringSeparator) {
			if (empty($queryString)) {
				$queryString = $withQueryStringSeparatorIfEmptyQueryString ? '?' : '';
			}
			else {
				$queryString = '?' . $queryString;
			}
		}

		return $queryString;
	}

	// ========== Building Query Strings ==========

	/**
	 * Builds a query string from parameters, with keys sorted alphabetically and RFC 3986 percent-encoding.
	 * Array values are rendered using the repeated 'key[]=value' notation (no numeric index) rather than the default 'key[0]=value' produced by http_build_query(), which is required by some signed APIs (e.g. HMAC request signing) so that the query string used to compute the signature is guaranteed to match the one actually sent.
	 * @param array $queryParams The query parameters to encode, may contain array values
	 * @return string The sorted, percent-encoded query string (without leading '?'), empty string if no parameters
	 * @link https://www.php.net/manual/en/function.http-build-query.php PHP http_build_query() documentation
	 */
	public static function buildSortedQueryString(array $queryParams): string
	{
		if (empty($queryParams)) {
			return '';
		}

		ksort($queryParams);

		$queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

		// http_build_query() indexes array values (key%5B0%5D=...); collapse to the plain repeated key%5B%5D=... notation
		return preg_replace('/%5B\d+%5D/', '%5B%5D', $queryString);
	}

	// ========== DEPRECATED METHODS (Backward Compatibility) ==========
	// Backward compatibility. Will be removed in a future major version. Please update your code to use the new method names.

	/**
	 * Checks the syntax of a URL
	 * @deprecated Use isValid() instead
	 * @param string $url the URL to check
	 * @param bool $checkScheme if true, also validates that the scheme is in the list of valid schemes (default: false)
	 * @return boolean true if the URL is syntactically correct, false otherwise
	 */
	public static function check(string $url, bool $checkScheme=false): bool
	{
		return self::isValid($url, $checkScheme);
	}

}