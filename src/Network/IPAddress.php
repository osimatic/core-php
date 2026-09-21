<?php

namespace Osimatic\Network;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class IPAddress
 * Provides utilities for IP address validation, parsing, and range checking
 */
class IPAddress
{
	/**
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 */
	public function __construct(
		private LoggerInterface $logger=new NullLogger(),
	) {}

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

	// ========== Validation ==========

	/**
	 * Checks the syntax of an IPv4 address
	 * @param string $ipAddress the IP address to check
	 * @return boolean true if the IP address is syntactically correct, false otherwise
	 */
	public static function isValid(string $ipAddress): bool
	{
		return self::isValidIpV4($ipAddress);
	}

	/**
	 * Checks the syntax of an IPv4 address
	 * @param string $ipAddress the IP address to check
	 * @return boolean true if the IP address is syntactically correct, false otherwise
	 */
	public static function isValidIpV4(string $ipAddress): bool
	{
		return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
	}

	/**
	 * Checks the syntax of an IPv6 address
	 * @param string $ipAddress the IP address to check
	 * @return boolean true if the IP address is syntactically correct, false otherwise
	 */
	public static function isValidIpV6(string $ipAddress): bool
	{
		return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
	}

	/**
	 * Checks the syntax of an IPv4 address range
	 * @param string $ipAddressRange the IP address range in format "start-end"
	 * @param string $rangeSeparator the separator between start and end IP (default: '-')
	 * @return bool true if both start and end IPs are valid IPv4 addresses, false otherwise
	 */
	public static function isValidRange(string $ipAddressRange, string $rangeSeparator='-'): bool
	{
		if (!str_contains($ipAddressRange, $rangeSeparator)) {
			return false;
		}

		[$rangeStartIp, $rangeEndIp] = explode($rangeSeparator, $ipAddressRange);
		return self::isValidIpV4(trim($rangeStartIp)) && self::isValidIpV4(trim($rangeEndIp));
	}

	/**
	 * Checks the syntax of an IPv6 address range
	 * @param string $ipAddressRange the IP address range in format "start-end"
	 * @param string $rangeSeparator the separator between start and end IP (default: '-')
	 * @return bool true if both start and end IPs are valid IPv6 addresses, false otherwise
	 */
	public static function isValidRangeOfIpV6(string $ipAddressRange, string $rangeSeparator='-'): bool
	{
		if (!str_contains($ipAddressRange, $rangeSeparator)) {
			return false;
		}

		[$rangeStartIp, $rangeEndIp] = explode($rangeSeparator, $ipAddressRange);
		return self::isValidIpV6(trim($rangeStartIp)) && self::isValidIpV6(trim($rangeEndIp));
	}

	// ========== IP Address Information ==========

	/**
	 * Extracts the client IP address from an HTTP request
	 * @param Request $request the HTTP request
	 * @return string the client IP address, empty string if not found
	 */
	public static function getFromRequest(Request $request): string
	{
		$ip = $request->getClientIp();
		if (empty($ip) || 'unknown' === $ip) {
			return $_SERVER['REMOTE_ADDR'] ?? '';
		}
		return $ip;
	}

	// ========== IP Address Ranges ==========

	/**
	 * Checks if an IP address is within a specified IP address range
	 * @param string $ipAddress the IP address to check
	 * @param string $ipAddressRange the IP address range in format "start-end"
	 * @param string $rangeSeparator the separator between start and end IP (default: '-')
	 * @return bool true if the IP is within the range, false otherwise
	 */
	public static function isInRangeOfIpAddressRange(string $ipAddress, string $ipAddressRange, string $rangeSeparator='-'): bool
	{
		if (!str_contains($ipAddressRange, $rangeSeparator)) {
			return false;
		}
		[$rangeStartIp, $rangeEndIp] = explode($rangeSeparator, $ipAddressRange);
		return self::isInRangeOfIpAddresses($ipAddress, $rangeStartIp, $rangeEndIp);
	}

	/**
	 * Checks if an IP address is between two IP addresses
	 * @param string $ipAddress the IP address to check
	 * @param string $ipAddressRangeBegin the start IP address of the range
	 * @param string $ipAddressRangeEnd the end IP address of the range
	 * @return bool true if the IP is within the range, false otherwise
	 */
	public static function isInRangeOfIpAddresses(string $ipAddress, string $ipAddressRangeBegin, string $ipAddressRangeEnd): bool
	{
		$ipAddressLongFormatRangeBegin = ip2long($ipAddressRangeBegin);
		$ipAddressLongFormatRangeEnd = ip2long($ipAddressRangeEnd);
		$ipAddressLongFormat = ip2long($ipAddress);
		return (($ipAddressLongFormatRangeBegin <= $ipAddressLongFormat) && ($ipAddressLongFormatRangeEnd >= $ipAddressLongFormat));
	}

	/**
	 * Checks if an IP address corresponds to a pattern (exact match or wildcard prefix with %)
	 * @param string $ipAddress the IP address to check
	 * @param string $ipAddressCompare the IP address pattern to compare (e.g., "192.168.%" for prefix matching)
	 * @return bool true if the IP matches the pattern, false otherwise
	 */
	public static function correspondToIpAddress(string $ipAddress, string $ipAddressCompare): bool
	{
		if ($ipAddress === $ipAddressCompare) {
			return true;
		}

		if (str_ends_with($ipAddressCompare, '%')) {
			$ipAddressPrefix = substr($ipAddressCompare, 0, -1);
			if (str_starts_with($ipAddress, $ipAddressPrefix)) {
				return true;
			}
		}

		return false;
	}

	// ========== IP Address Ranges (CIDR notation) ==========

	/**
	 * Checks if an IP address is within a CIDR notation range
	 * @param string $ip the IP address to check
	 * @param string $range the CIDR range (e.g., "192.168.1.0/24")
	 * @return bool true if the IP is within the CIDR range, false otherwise
	 */
	public static function isInRangeOfIpAddressesCidr(string $ip, string $range): bool
	{
		if (!str_contains($range, '/')) {
			return false;
		}

		[$subnet, $bits] = explode('/', $range, 2);
		$intIp = ip2long($ip);
		$subnet = ip2long($subnet);
		$mask = -1 << (32 - (int) $bits);
		$subnet &= $mask; // nb: in case the supplied subnet wasn't correctly aligned
		return ($intIp & $mask) === $subnet;
	}

	/**
	 * Checks if an IP address is in a list of IP addresses, ranges, or hostnames
	 * @param string $ipAddressToCheck the IP address to check
	 * @param array $ipAddressList array of IP addresses, IP ranges, or DNS hostnames
	 * @return bool true if the IP is found in the list, false otherwise
	 */
	public static function isIpAddressInListOfIpAddress(string $ipAddressToCheck, array $ipAddressList): bool
	{
		foreach ($ipAddressList as $ipAddress) {
			if (self::isValidRange($ipAddress)) {
				// It's an IP range
				if (self::isInRangeOfIpAddressRange($ipAddressToCheck, $ipAddress)) {
					return true;
				}
				continue;
			}

			if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				// It's a DNS name
				$ipAddress = gethostbyname($ipAddress);
			}

			if (self::correspondToIpAddress($ipAddressToCheck, $ipAddress)) {
				return true;
			}
		}
		return false;
	}








	// ========== DEPRECATED METHODS (Backward Compatibility) ==========
	// Backward compatibility. Will be removed in a future major version. Please update your code to use the new method names.

	/**
	 * Checks if an IP address is using a VPN (deprecated - use VPNAPI or Incolumitas directly)
	 * @deprecated
	 * @param string $ipAddress the IP address to check
	 * @return bool true if VPN detected, false otherwise
	 */
	public function isVpn(string $ipAddress): bool
	{
		$api = new Incolumitas($this->logger);
		$result = $api->getIpInfos($ipAddress);
		if (null === $result) {
			return false;
		}
		return Incolumitas::isVpn($result);
	}

	/**
	 * Checks the syntax of an IPv4 address
	 * @deprecated Use isValid() instead
	 * @param string $ipAddress the IP address to check
	 * @return boolean true if the IP address is syntactically correct, false otherwise
	 */
	public static function check(string $ipAddress): bool
	{
		return self::isValid($ipAddress);
	}

	/**
	 * Checks the syntax of an IPv4 address
	 * @deprecated Use isValidIpV4() instead
	 * @param string $ipAddress the IP address to check
	 * @return boolean true if the IP address is syntactically correct, false otherwise
	 */
	public static function checkIpV4(string $ipAddress): bool
	{
		return self::isValidIpV4($ipAddress);
	}

	/**
	 * Checks the syntax of an IPv6 address
	 * @deprecated Use isValidIpV6() instead
	 * @param string $ipAddress the IP address to check
	 * @return boolean true if the IP address is syntactically correct, false otherwise
	 */
	public static function checkIpV6(string $ipAddress): bool
	{
		return self::isValidIpV6($ipAddress);
	}

	/**
	 * Checks the syntax of an IPv4 address range
	 * @deprecated Use isValidRange() instead
	 * @param string $ipAddressRange the IP address range in format "start-end"
	 * @param string $rangeSeparator the separator between start and end IP (default: '-')
	 * @return bool true if both start and end IPs are valid IPv4 addresses, false otherwise
	 */
	public static function checkRange(string $ipAddressRange, string $rangeSeparator='-'): bool
	{
		return self::isValidRange($ipAddressRange, $rangeSeparator);
	}

	/**
	 * Checks the syntax of an IPv6 address range
	 * @deprecated Use isValidRangeOfIpV6() instead
	 * @param string $ipAddressRange the IP address range in format "start-end"
	 * @param string $rangeSeparator the separator between start and end IP (default: '-')
	 * @return bool true if both start and end IPs are valid IPv6 addresses, false otherwise
	 */
	public static function checkRangeOfIpV6(string $ipAddressRange, string $rangeSeparator='-'): bool
	{
		return self::isValidRangeOfIpV6($ipAddressRange, $rangeSeparator);
	}

}