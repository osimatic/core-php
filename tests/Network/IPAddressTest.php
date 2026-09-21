<?php

declare(strict_types=1);

namespace Tests\Network;

use Osimatic\Network\IPAddress;
use PHPUnit\Framework\TestCase;

final class IPAddressTest extends TestCase
{
	/* ===================== isValid() ===================== */

	public function testIsValidWithValidIpV4(): void
	{
		$this->assertTrue(IPAddress::isValid('192.168.1.1'));
		$this->assertTrue(IPAddress::isValid('10.0.0.1'));
		$this->assertTrue(IPAddress::isValid('255.255.255.255'));
		$this->assertTrue(IPAddress::isValid('0.0.0.0'));
		$this->assertTrue(IPAddress::isValid('127.0.0.1'));
	}

	public function testIsValidWithInvalidIpV4(): void
	{
		$this->assertFalse(IPAddress::isValid('999.999.999.999'));
		$this->assertFalse(IPAddress::isValid('192.168.1'));
		$this->assertFalse(IPAddress::isValid('192.168.1.1.1'));
		$this->assertFalse(IPAddress::isValid('not-an-ip'));
		$this->assertFalse(IPAddress::isValid(''));
		$this->assertFalse(IPAddress::isValid('256.1.1.1'));
	}

	public function testIsValidWithIpV6(): void
	{
		// isValid() valide uniquement les IPv4, pas les IPv6
		$this->assertFalse(IPAddress::isValid('::1'));
		$this->assertFalse(IPAddress::isValid('2001:0db8:85a3::8a2e:0370:7334'));
	}

	/* ===================== isValidIpV4() ===================== */

	public function testIsValidIpV4WithValidAddresses(): void
	{
		$this->assertTrue(IPAddress::isValidIpV4('192.168.1.1'));
		$this->assertTrue(IPAddress::isValidIpV4('10.0.0.1'));
		$this->assertTrue(IPAddress::isValidIpV4('172.16.0.1'));
		$this->assertTrue(IPAddress::isValidIpV4('8.8.8.8'));
		$this->assertTrue(IPAddress::isValidIpV4('1.1.1.1'));
	}

	public function testIsValidIpV4WithInvalidAddresses(): void
	{
		$this->assertFalse(IPAddress::isValidIpV4('999.999.999.999'));
		$this->assertFalse(IPAddress::isValidIpV4('192.168.1'));
		$this->assertFalse(IPAddress::isValidIpV4('192.168.1.1.1'));
		$this->assertFalse(IPAddress::isValidIpV4('256.1.1.1'));
		$this->assertFalse(IPAddress::isValidIpV4('-1.0.0.0'));
	}

	/* ===================== isValidIpV6() ===================== */

	public function testIsValidIpV6WithValidAddresses(): void
	{
		$this->assertTrue(IPAddress::isValidIpV6('::1'));
		$this->assertTrue(IPAddress::isValidIpV6('2001:0db8:85a3::8a2e:0370:7334'));
		$this->assertTrue(IPAddress::isValidIpV6('2001:db8::1'));
		$this->assertTrue(IPAddress::isValidIpV6('fe80::1'));
		$this->assertTrue(IPAddress::isValidIpV6('::'));
	}

	public function testIsValidIpV6WithInvalidAddresses(): void
	{
		$this->assertFalse(IPAddress::isValidIpV6('192.168.1.1'));
		$this->assertFalse(IPAddress::isValidIpV6('gggg::1'));
		$this->assertFalse(IPAddress::isValidIpV6('not-an-ipv6'));
		$this->assertFalse(IPAddress::isValidIpV6(''));
	}

	/* ===================== isValidRange() ===================== */

	public function testIsValidRangeWithValidRanges(): void
	{
		$this->assertTrue(IPAddress::isValidRange('192.168.1.0-192.168.1.255'));
		$this->assertTrue(IPAddress::isValidRange('10.0.0.0-10.255.255.255'));
		$this->assertTrue(IPAddress::isValidRange('172.16.0.0-172.31.255.255'));
	}

	public function testIsValidRangeWithSpaces(): void
	{
		$this->assertTrue(IPAddress::isValidRange('192.168.1.0 - 192.168.1.255'));
	}

	public function testIsValidRangeWithInvalidRanges(): void
	{
		$this->assertFalse(IPAddress::isValidRange('192.168.1.0'));
		$this->assertFalse(IPAddress::isValidRange('192.168.1.0-999.999.999.999'));
		$this->assertFalse(IPAddress::isValidRange('not-an-ip-range'));
		$this->assertFalse(IPAddress::isValidRange(''));
	}

	public function testIsValidRangeWithCustomSeparator(): void
	{
		$this->assertTrue(IPAddress::isValidRange('192.168.1.0|192.168.1.255', '|'));
		$this->assertTrue(IPAddress::isValidRange('192.168.1.0..192.168.1.255', '..'));
	}

	/* ===================== isValidRangeOfIpV6() ===================== */

	public function testIsValidRangeOfIpV6WithValidRanges(): void
	{
		$this->assertTrue(IPAddress::isValidRangeOfIpV6('2001:db8::1-2001:db8::ffff'));
		$this->assertTrue(IPAddress::isValidRangeOfIpV6('fe80::1-fe80::ff'));
	}

	public function testIsValidRangeOfIpV6WithInvalidRanges(): void
	{
		$this->assertFalse(IPAddress::isValidRangeOfIpV6('192.168.1.0-192.168.1.255'));
		$this->assertFalse(IPAddress::isValidRangeOfIpV6('::1'));
		$this->assertFalse(IPAddress::isValidRangeOfIpV6(''));
	}

	/* ===================== isInRangeOfIpAddressRange() ===================== */

	public function testIsInRangeOfIpAddressRangeWithIpInRange(): void
	{
		$this->assertTrue(IPAddress::isInRangeOfIpAddressRange('192.168.1.100', '192.168.1.0-192.168.1.255'));
		$this->assertTrue(IPAddress::isInRangeOfIpAddressRange('10.0.0.50', '10.0.0.0-10.0.0.255'));
		$this->assertTrue(IPAddress::isInRangeOfIpAddressRange('192.168.1.0', '192.168.1.0-192.168.1.255'));
		$this->assertTrue(IPAddress::isInRangeOfIpAddressRange('192.168.1.255', '192.168.1.0-192.168.1.255'));
	}

	public function testIsInRangeOfIpAddressRangeWithIpOutOfRange(): void
	{
		$this->assertFalse(IPAddress::isInRangeOfIpAddressRange('192.168.2.100', '192.168.1.0-192.168.1.255'));
		$this->assertFalse(IPAddress::isInRangeOfIpAddressRange('10.1.0.50', '10.0.0.0-10.0.0.255'));
	}

	public function testIsInRangeOfIpAddressRangeWithInvalidRange(): void
	{
		$this->assertFalse(IPAddress::isInRangeOfIpAddressRange('192.168.1.100', '192.168.1.0'));
	}

	/* ===================== isInRangeOfIpAddresses() ===================== */

	public function testIsInRangeOfIpAddressesWithIpInRange(): void
	{
		$this->assertTrue(IPAddress::isInRangeOfIpAddresses('192.168.1.100', '192.168.1.0', '192.168.1.255'));
		$this->assertTrue(IPAddress::isInRangeOfIpAddresses('10.0.0.50', '10.0.0.0', '10.0.0.255'));
	}

	public function testIsInRangeOfIpAddressesWithIpOutOfRange(): void
	{
		$this->assertFalse(IPAddress::isInRangeOfIpAddresses('192.168.2.100', '192.168.1.0', '192.168.1.255'));
		$this->assertFalse(IPAddress::isInRangeOfIpAddresses('10.1.0.50', '10.0.0.0', '10.0.0.255'));
	}

	public function testIsInRangeOfIpAddressesAtBoundaries(): void
	{
		$this->assertTrue(IPAddress::isInRangeOfIpAddresses('192.168.1.0', '192.168.1.0', '192.168.1.255'));
		$this->assertTrue(IPAddress::isInRangeOfIpAddresses('192.168.1.255', '192.168.1.0', '192.168.1.255'));
	}

	/* ===================== correspondToIpAddress() ===================== */

	public function testCorrespondToIpAddressWithExactMatch(): void
	{
		$this->assertTrue(IPAddress::correspondToIpAddress('192.168.1.1', '192.168.1.1'));
		$this->assertTrue(IPAddress::correspondToIpAddress('10.0.0.1', '10.0.0.1'));
	}

	public function testCorrespondToIpAddressWithNoMatch(): void
	{
		$this->assertFalse(IPAddress::correspondToIpAddress('192.168.1.1', '192.168.1.2'));
		$this->assertFalse(IPAddress::correspondToIpAddress('10.0.0.1', '10.0.0.2'));
	}

	public function testCorrespondToIpAddressWithWildcard(): void
	{
		$this->assertTrue(IPAddress::correspondToIpAddress('192.168.1.1', '192.168.1.%'));
		$this->assertTrue(IPAddress::correspondToIpAddress('192.168.1.100', '192.168.1.%'));
		$this->assertTrue(IPAddress::correspondToIpAddress('192.168.1.255', '192.168.1.%'));
		$this->assertTrue(IPAddress::correspondToIpAddress('192.168.100.1', '192.168.%'));
		$this->assertTrue(IPAddress::correspondToIpAddress('192.100.1.1', '192.%'));
	}

	public function testCorrespondToIpAddressWithWildcardNoMatch(): void
	{
		$this->assertFalse(IPAddress::correspondToIpAddress('192.168.2.1', '192.168.1.%'));
		$this->assertFalse(IPAddress::correspondToIpAddress('10.0.0.1', '192.168.%'));
	}

	/* ===================== isInRangeOfIpAddressesCidr() ===================== */

	public function testIsInRangeOfIpAddressesCidrWithIpInRange(): void
	{
		$this->assertTrue(IPAddress::isInRangeOfIpAddressesCidr('192.168.1.100', '192.168.1.0/24'));
		$this->assertTrue(IPAddress::isInRangeOfIpAddressesCidr('10.0.0.50', '10.0.0.0/24'));
		$this->assertTrue(IPAddress::isInRangeOfIpAddressesCidr('172.16.50.1', '172.16.0.0/16'));
	}

	public function testIsInRangeOfIpAddressesCidrWithIpOutOfRange(): void
	{
		$this->assertFalse(IPAddress::isInRangeOfIpAddressesCidr('192.168.2.100', '192.168.1.0/24'));
		$this->assertFalse(IPAddress::isInRangeOfIpAddressesCidr('10.1.0.50', '10.0.0.0/24'));
	}

	public function testIsInRangeOfIpAddressesCidrWithDifferentMasks(): void
	{
		// /32 = une seule adresse
		$this->assertTrue(IPAddress::isInRangeOfIpAddressesCidr('192.168.1.1', '192.168.1.1/32'));
		$this->assertFalse(IPAddress::isInRangeOfIpAddressesCidr('192.168.1.2', '192.168.1.1/32'));

		// /8 = très large plage
		$this->assertTrue(IPAddress::isInRangeOfIpAddressesCidr('10.50.100.200', '10.0.0.0/8'));
		$this->assertTrue(IPAddress::isInRangeOfIpAddressesCidr('10.255.255.255', '10.0.0.0/8'));
		$this->assertFalse(IPAddress::isInRangeOfIpAddressesCidr('11.0.0.1', '10.0.0.0/8'));
	}

	/* ===================== getFromRequest() ===================== */

	public function testGetFromRequest(): void
	{
		// Case: getClientIp() returns valid IP
		$requestMock = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)
			->onlyMethods(['getClientIp'])
			->getMock();
		$requestMock->method('getClientIp')->willReturn('192.168.1.100');
		$this->assertSame('192.168.1.100', IPAddress::getFromRequest($requestMock));

		// Case: getClientIp() returns 'unknown' → fallback to $_SERVER
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$requestMock = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)
			->onlyMethods(['getClientIp'])
			->getMock();
		$requestMock->method('getClientIp')->willReturn('unknown');
		$this->assertSame('10.0.0.1', IPAddress::getFromRequest($requestMock));

		// Case: getClientIp() returns empty → fallback to $_SERVER
		$_SERVER['REMOTE_ADDR'] = '172.16.0.50';
		$requestMock = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)
			->onlyMethods(['getClientIp'])
			->getMock();
		$requestMock->method('getClientIp')->willReturn('');
		$this->assertSame('172.16.0.50', IPAddress::getFromRequest($requestMock));

		// Case: getClientIp() empty and no $_SERVER → empty string
		unset($_SERVER['REMOTE_ADDR']);
		$requestMock = $this->getMockBuilder(\Symfony\Component\HttpFoundation\Request::class)
			->onlyMethods(['getClientIp'])
			->getMock();
		$requestMock->method('getClientIp')->willReturn('');
		$this->assertSame('', IPAddress::getFromRequest($requestMock));
	}

	/* ===================== isIpAddressInListOfIpAddress() ===================== */

	public function testIsIpAddressInListOfIpAddressWithExactMatch(): void
	{
		$list = ['192.168.1.1', '192.168.1.2', '192.168.1.3'];
		$this->assertTrue(IPAddress::isIpAddressInListOfIpAddress('192.168.1.1', $list));
		$this->assertTrue(IPAddress::isIpAddressInListOfIpAddress('192.168.1.2', $list));
		$this->assertFalse(IPAddress::isIpAddressInListOfIpAddress('192.168.1.4', $list));
	}

	public function testIsIpAddressInListOfIpAddressWithRange(): void
	{
		$list = ['192.168.1.0-192.168.1.255', '10.0.0.0-10.0.0.255'];
		$this->assertTrue(IPAddress::isIpAddressInListOfIpAddress('192.168.1.100', $list));
		$this->assertTrue(IPAddress::isIpAddressInListOfIpAddress('10.0.0.50', $list));
		$this->assertFalse(IPAddress::isIpAddressInListOfIpAddress('192.168.2.1', $list));
	}

	public function testIsIpAddressInListOfIpAddressWithWildcard(): void
	{
		$list = ['192.168.1.%', '10.0.0.%'];
		$this->assertTrue(IPAddress::isIpAddressInListOfIpAddress('192.168.1.1', $list));
		$this->assertTrue(IPAddress::isIpAddressInListOfIpAddress('192.168.1.255', $list));
		$this->assertTrue(IPAddress::isIpAddressInListOfIpAddress('10.0.0.1', $list));
		$this->assertFalse(IPAddress::isIpAddressInListOfIpAddress('192.168.2.1', $list));
	}

	public function testIsIpAddressInListOfIpAddressWithMixedFormats(): void
	{
		$list = ['192.168.1.1', '192.168.2.0-192.168.2.255', '10.0.0.%'];
		$this->assertTrue(IPAddress::isIpAddressInListOfIpAddress('192.168.1.1', $list));
		$this->assertTrue(IPAddress::isIpAddressInListOfIpAddress('192.168.2.100', $list));
		$this->assertTrue(IPAddress::isIpAddressInListOfIpAddress('10.0.0.50', $list));
		$this->assertFalse(IPAddress::isIpAddressInListOfIpAddress('192.168.3.1', $list));
	}

	public function testIsIpAddressInListOfIpAddressWithEmptyList(): void
	{
		$this->assertFalse(IPAddress::isIpAddressInListOfIpAddress('192.168.1.1', []));
	}
}
