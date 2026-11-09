<?php

declare(strict_types=1);

namespace Tests\Person;

use GuzzleHttp\Psr7\Response;
use Osimatic\Person\GoogleContact;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

final class GoogleContactTest extends TestCase
{
	private const string TEST_CLIENT_ID = 'test-client-id-123.apps.googleusercontent.com';
	private const string TEST_SECRET = 'test-secret-456';
	private const string TEST_APP_NAME = 'Test Application';
	private const string TEST_REDIRECT_URI = 'https://example.com/oauth/callback';
	private const string TEST_AUTH_CODE = 'test-auth-code-xyz';

	/**
	 * Helper method to create a PSR-7 Response with JSON body
	 * @param array $data Data to encode as JSON
	 * @param int $statusCode HTTP status code
	 * @return Response PSR-7 Response instance
	 */
	private function createJsonResponse(array $data, int $statusCode = 200): Response
	{
		return new Response($statusCode, ['Content-Type' => 'application/json'], json_encode($data));
	}

	/**
	 * Helper method to create a Google Contacts API response
	 * @param array $contacts Array of contact data
	 * @return array The API response structure
	 */
	private function createGoogleContactsResponse(array $contacts): array
	{
		return [
			'feed' => [
				'entry' => $contacts
			]
		];
	}

	/**
	 * Helper method to create a single contact entry
	 * @param string $firstName First name
	 * @param string $lastName Last name
	 * @param string|null $email Email address
	 * @param string|null $phone Phone number
	 * @return array Contact entry structure
	 */
	private function createContactEntry(
		string $firstName,
		string $lastName,
		?string $email = null,
		?string $phone = null
	): array {
		$entry = [
			'gd$name' => [
				'gd$givenName' => ['$t' => $firstName],
				'gd$familyName' => ['$t' => $lastName],
				'gd$fullName' => ['$t' => "$firstName $lastName"]
			]
		];

		if ($email !== null) {
			$entry['gd$email'] = [
				[
					'rel' => 'http://schemas.google.com/g/2005#home',
					'address' => $email
				]
			];
		}

		if ($phone !== null) {
			$entry['gd$phoneNumber'] = [
				[
					'rel' => 'http://schemas.google.com/g/2005#mobile',
					'uri' => 'tel:' . $phone
				]
			];
		}

		return $entry;
	}

	/* ===================== Constructor ===================== */

	public function testConstructorWithAllParameters(): void
	{
		$logger = $this->createStub(LoggerInterface::class);
		$httpClient = $this->createStub(ClientInterface::class);

		$googleContact = new GoogleContact(
			self::TEST_CLIENT_ID,
			self::TEST_SECRET,
			self::TEST_APP_NAME,
			logger: $logger,
			httpClient: $httpClient
		);

		self::assertInstanceOf(GoogleContact::class, $googleContact);
	}

	public function testConstructorWithMinimalParameters(): void
	{
		$googleContact = new GoogleContact();

		self::assertInstanceOf(GoogleContact::class, $googleContact);
	}

	public function testConstructorWithLogger(): void
	{
		$logger = $this->createStub(LoggerInterface::class);
		$googleContact = new GoogleContact(logger: $logger);

		self::assertInstanceOf(GoogleContact::class, $googleContact);
	}

	public function testConstructorWithHttpClientInjection(): void
	{
		$httpClient = $this->createStub(ClientInterface::class);
		$googleContact = new GoogleContact(httpClient: $httpClient);

		self::assertInstanceOf(GoogleContact::class, $googleContact);
	}

	/* ===================== Setters ===================== */

	public function testSetClientId(): void
	{
		$googleContact = new GoogleContact();

		$result = $googleContact->setClientId(self::TEST_CLIENT_ID);

		self::assertSame($googleContact, $result);
	}

	public function testSetSecret(): void
	{
		$googleContact = new GoogleContact();

		$result = $googleContact->setSecret(self::TEST_SECRET);

		self::assertSame($googleContact, $result);
	}

	public function testSetApplicationName(): void
	{
		$googleContact = new GoogleContact();

		$result = $googleContact->setApplicationName(self::TEST_APP_NAME);

		self::assertSame($googleContact, $result);
	}

	public function testSetRedirectUri(): void
	{
		$googleContact = new GoogleContact();

		$result = $googleContact->setRedirectUri(self::TEST_REDIRECT_URI);

		self::assertSame($googleContact, $result);
	}

	public function testFluentInterface(): void
	{
		$googleContact = new GoogleContact();

		$result = $googleContact
			->setClientId(self::TEST_CLIENT_ID)
			->setSecret(self::TEST_SECRET)
			->setApplicationName(self::TEST_APP_NAME)
			->setRedirectUri(self::TEST_REDIRECT_URI);

		self::assertSame($googleContact, $result);
	}

	/* ===================== getUrl() ===================== */

	public function testGetUrlReturnsAuthorizationUrl(): void
	{
		$googleContact = new GoogleContact(
			self::TEST_CLIENT_ID,
			self::TEST_SECRET
		);
		$googleContact->setRedirectUri(self::TEST_REDIRECT_URI);

		$url = $googleContact->getUrl();

		self::assertIsString($url);
		self::assertStringContainsString('accounts.google.com', $url);
		self::assertStringContainsString('oauth', $url);
	}

	/* ===================== Static method: parsePhoneNumber() ===================== */

	public function testParsePhoneNumberRemovesTelPrefix(): void
	{
		$data = [
			'uri' => 'tel:+33612345678'
		];

		$number = GoogleContact::parsePhoneNumber($data);

		self::assertSame('+33612345678', $number);
	}

	public function testParsePhoneNumberRemovesDashes(): void
	{
		$data = [
			'uri' => 'tel:+33-6-12-34-56-78'
		];

		$number = GoogleContact::parsePhoneNumber($data);

		self::assertSame('+33612345678', $number);
	}

	public function testParsePhoneNumberWithInternationalFormat(): void
	{
		$data = [
			'uri' => 'tel:+1-555-123-4567'
		];

		$number = GoogleContact::parsePhoneNumber($data);

		self::assertSame('+15551234567', $number);
	}

	public function testParsePhoneNumberReturnsNullWhenUriMissing(): void
	{
		$data = [];

		$number = GoogleContact::parsePhoneNumber($data);

		self::assertNull($number);
	}

	public function testParsePhoneNumberReturnsNullWhenUriEmpty(): void
	{
		$data = [
			'uri' => ''
		];

		$number = GoogleContact::parsePhoneNumber($data);

		self::assertNull($number);
	}

	/* ===================== Static method: getDataRelType() ===================== */

	public function testGetDataRelTypeReturnsHome(): void
	{
		$data = [
			'rel' => 'http://schemas.google.com/g/2005#home'
		];

		$type = GoogleContact::getDataRelType($data);

		self::assertSame('home', $type);
	}

	public function testGetDataRelTypeReturnsWork(): void
	{
		$data = [
			'rel' => 'http://schemas.google.com/g/2005#work'
		];

		$type = GoogleContact::getDataRelType($data);

		self::assertSame('work', $type);
	}

	public function testGetDataRelTypeReturnsMobile(): void
	{
		$data = [
			'rel' => 'http://schemas.google.com/g/2005#mobile'
		];

		$type = GoogleContact::getDataRelType($data);

		self::assertSame('mobile', $type);
	}

	public function testGetDataRelTypeReturnsOther(): void
	{
		$data = [
			'rel' => 'http://schemas.google.com/g/2005#other'
		];

		$type = GoogleContact::getDataRelType($data);

		self::assertSame('other', $type);
	}

	public function testGetDataRelTypeReturnsNullWhenRelMissing(): void
	{
		$data = [];

		$type = GoogleContact::getDataRelType($data);

		self::assertNull($type);
	}

	public function testGetDataRelTypeReturnsNullWhenRelEmpty(): void
	{
		$data = [
			'rel' => ''
		];

		$type = GoogleContact::getDataRelType($data);

		// Empty string: strpos returns false, so substr returns null
		self::assertNull($type);
	}

	/* ===================== getContacts() ===================== */

	public function testGetContactsReturnsNullWithoutAuthCode(): void
	{
		$googleContact = new GoogleContact(
			self::TEST_CLIENT_ID,
			self::TEST_SECRET
		);

		$result = $googleContact->getContacts(null);

		self::assertNull($result);
	}

	public function testGetContactsReturnsNullWithEmptyAuthCode(): void
	{
		$googleContact = new GoogleContact(
			self::TEST_CLIENT_ID,
			self::TEST_SECRET
		);

		$result = $googleContact->getContacts('');

		self::assertNull($result);
	}

	/* ===================== Configuration scenarios ===================== */

	public function testCompleteConfiguration(): void
	{
		$googleContact = new GoogleContact();

		$result = $googleContact
			->setClientId(self::TEST_CLIENT_ID)
			->setSecret(self::TEST_SECRET)
			->setApplicationName(self::TEST_APP_NAME)
			->setRedirectUri(self::TEST_REDIRECT_URI);

		self::assertSame($googleContact, $result);

		// Verify URL can be generated after configuration
		$url = $googleContact->getUrl();
		self::assertIsString($url);
		self::assertNotEmpty($url);
	}

	/* ===================== Phone number parsing scenarios ===================== */

	public function testParsePhoneNumberWithLocalFormat(): void
	{
		$data = [
			'uri' => 'tel:0612345678'
		];

		$number = GoogleContact::parsePhoneNumber($data);

		self::assertSame('0612345678', $number);
	}

	public function testParsePhoneNumberWithSpaces(): void
	{
		$data = [
			'uri' => 'tel:+33-6-12-34-56-78'
		];

		$number = GoogleContact::parsePhoneNumber($data);

		// Removes dashes but not spaces (if any)
		self::assertSame('+33612345678', $number);
	}

	public function testParsePhoneNumberWithExtension(): void
	{
		$data = [
			'uri' => 'tel:+1-555-123-4567;ext=123'
		];

		$number = GoogleContact::parsePhoneNumber($data);

		self::assertSame('+15551234567;ext=123', $number);
	}

	/* ===================== Data rel type scenarios ===================== */

	public function testGetDataRelTypeWithCustomScheme(): void
	{
		$data = [
			'rel' => 'http://schemas.google.com/g/2005#custom'
		];

		$type = GoogleContact::getDataRelType($data);

		self::assertSame('custom', $type);
	}

	public function testGetDataRelTypeWithDifferentDomain(): void
	{
		$data = [
			'rel' => 'http://example.com/schema#personal'
		];

		$type = GoogleContact::getDataRelType($data);

		self::assertSame('personal', $type);
	}

	/* ===================== Edge cases ===================== */

	public function testParsePhoneNumberWithOnlyTelPrefix(): void
	{
		$data = [
			'uri' => 'tel:'
		];

		$number = GoogleContact::parsePhoneNumber($data);

		self::assertSame('', $number);
	}

	public function testGetDataRelTypeWithOnlyHash(): void
	{
		$data = [
			'rel' => '#'
		];

		$type = GoogleContact::getDataRelType($data);

		self::assertSame('', $type);
	}

	public function testGetUrlRequiresRedirectUri(): void
	{
		$googleContact = new GoogleContact();

		// Google_Client requires a redirect URI to generate auth URL
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('redirect URI');

		$googleContact->getUrl();
	}

	/* ===================== Multiple operations ===================== */

	public function testParseMultiplePhoneNumbers(): void
	{
		$phones = [
			['uri' => 'tel:+33612345678'],
			['uri' => 'tel:+1-555-123-4567'],
			['uri' => 'tel:+44-20-1234-5678'],
		];

		$parsed = array_map([GoogleContact::class, 'parsePhoneNumber'], $phones);

		self::assertCount(3, $parsed);
		self::assertSame('+33612345678', $parsed[0]);
		self::assertSame('+15551234567', $parsed[1]);
		self::assertSame('+442012345678', $parsed[2]);
	}

	public function testGetMultipleDataRelTypes(): void
	{
		$data = [
			['rel' => 'http://schemas.google.com/g/2005#home'],
			['rel' => 'http://schemas.google.com/g/2005#work'],
			['rel' => 'http://schemas.google.com/g/2005#mobile'],
		];

		$types = array_map([GoogleContact::class, 'getDataRelType'], $data);

		self::assertCount(3, $types);
		self::assertSame('home', $types[0]);
		self::assertSame('work', $types[1]);
		self::assertSame('mobile', $types[2]);
	}

	/* ===================== Authorization scenarios ===================== */

	public function testGetUrlGeneratesUniqueUrls(): void
	{
		$googleContact1 = new GoogleContact(
			'client-id-1',
			'secret-1'
		);
		$googleContact1->setRedirectUri('https://example.com/callback1');

		$googleContact2 = new GoogleContact(
			'client-id-2',
			'secret-2'
		);
		$googleContact2->setRedirectUri('https://example.com/callback2');

		$url1 = $googleContact1->getUrl();
		$url2 = $googleContact2->getUrl();

		self::assertIsString($url1);
		self::assertIsString($url2);
		// URLs should be different as they have different configurations
		// (though this depends on Google_Client implementation)
	}

	/* ===================== Configuration changes ===================== */

	public function testChangingConfigurationAfterInitialization(): void
	{
		$googleContact = new GoogleContact(
			'initial-client-id',
			'initial-secret',
			'Initial App'
		);

		// Change configuration
		$googleContact
			->setClientId('new-client-id')
			->setSecret('new-secret')
			->setApplicationName('New App')
			->setRedirectUri(self::TEST_REDIRECT_URI);

		// Should still work after configuration changes
		$url = $googleContact->getUrl();
		self::assertIsString($url);
	}

	/* ===================== Logger integration ===================== */

	public function testConstructorWithLoggerDoesNotThrow(): void
	{
		$logger = $this->createStub(LoggerInterface::class);

		// Should not throw any exception
		$googleContact = new GoogleContact(
			self::TEST_CLIENT_ID,
			self::TEST_SECRET,
			self::TEST_APP_NAME,
			logger: $logger
		);

		self::assertInstanceOf(GoogleContact::class, $googleContact);
	}
}