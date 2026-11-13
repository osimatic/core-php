<?php

namespace Tests\Security;

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use Osimatic\Security\PasskeyCredential;
use Osimatic\Security\WebAuthnService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Only the parts that do not need a real authenticator are covered here (options generation, rejection of invalid responses).
 * A successful registration or authentication must be checked end to end in a browser.
 */
#[AllowMockObjectsWithoutExpectations]
class WebAuthnServiceTest extends TestCase
{
	private LoggerInterface $logger;
	private WebAuthnService $service;

	protected function setUp(): void
	{
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new WebAuthnService('Test Site', 'example.com', $this->logger);
	}

	private static function toBase64Url(string $binary): string
	{
		return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
	}

	// ========================================
	// Constructor & Configuration Tests
	// ========================================

	public function testConstructor(): void
	{
		self::assertInstanceOf(WebAuthnService::class, new WebAuthnService('Test Site', 'example.com'));

		// Custom WebAuthn factory, called with the relying party name and ID
		$calls = [];
		$factory = function (string $name, string $id) use (&$calls): WebAuthn {
			$calls[] = [$name, $id];
			return new WebAuthn($name, $id, null, true);
		};
		new WebAuthnService('Test Site', 'example.com', $this->logger, $factory);
		self::assertSame([['Test Site', 'example.com']], $calls);
	}

	// ========================================
	// Registration Methods Tests
	// ========================================

	public function testCreateRegistrationOptions(): void
	{
		$result = $this->service->createRegistrationOptions("\x01\x02user", 'admin', 'Administrator', [self::toBase64Url("\x09\x08cred")]);
		$options = json_decode(json_encode($result->getOptions()), true);

		// Relying party and user
		self::assertSame('example.com', $options['publicKey']['rp']['id']);
		self::assertSame('Test Site', $options['publicKey']['rp']['name']);
		self::assertSame('admin', $options['publicKey']['user']['name']);
		self::assertSame('Administrator', $options['publicKey']['user']['displayName']);
		self::assertSame(self::toBase64Url("\x01\x02user"), $options['publicKey']['user']['id']);

		// The returned raw challenge is the one sent to the browser (base64url), and is random
		self::assertNotSame('', $result->getChallenge());
		self::assertSame(self::toBase64Url($result->getChallenge()), $options['publicKey']['challenge']);
		self::assertNotSame($result->getChallenge(), $this->service->createRegistrationOptions('u', 'admin', 'Administrator')->getChallenge());

		// User verification is required, already registered credentials are excluded
		self::assertSame('required', $options['publicKey']['authenticatorSelection']['userVerification']);
		self::assertSame(self::toBase64Url("\x09\x08cred"), $options['publicKey']['excludeCredentials'][0]['id']);

		// An excluded credential ID that is not valid base64url (standard Base64 characters, garbage, empty) is refused
		foreach (['AQL/', '!!!', ''] as $invalidCredentialId) {
			try {
				$this->service->createRegistrationOptions('user', 'admin', 'Administrator', [$invalidCredentialId]);
				self::fail('Expected exception for the excluded credential ID "' . $invalidCredentialId . '"');
			} catch (\InvalidArgumentException) {
				self::assertTrue(true);
			}
		}
	}

	public function testVerifyRegistration(): void
	{
		$challenge = $this->service->createRegistrationOptions('user', 'admin', 'Administrator')->getChallenge();

		// Invalid data is rejected and logged, never throws
		$this->logger->expects(self::exactly(2))->method('warning');
		self::assertNull($this->service->verifyRegistration(self::toBase64Url('not json'), self::toBase64Url('not cbor'), $challenge, 'user'));

		// Values that are not valid base64url are rejected and logged as well
		self::assertNull($this->service->verifyRegistration('not base64url!', self::toBase64Url('not cbor'), $challenge, 'user'));

		// Valid registration (WebAuthn library mocked): the browser values are decoded before reaching the library, and the credential ID given back as binary is kept as base64url
		$data = (object)['credentialId' => "\x01cred", 'credentialPublicKey' => 'PEM', 'signatureCounter' => 4];
		$webAuthn = $this->createMock(WebAuthn::class);
		$webAuthn->expects(self::once())->method('processCreate')->with('client', 'attestation', 'challenge', true)->willReturn($data);
		$service = new WebAuthnService('Test Site', 'example.com', $this->createStub(LoggerInterface::class), fn() => $webAuthn);
		$credential = $service->verifyRegistration(self::toBase64Url('client'), self::toBase64Url('attestation'), 'challenge', 'user-1', 'My phone');
		self::assertNotNull($credential);
		self::assertSame(self::toBase64Url("\x01cred"), $credential->getCredentialId());
		self::assertSame('PEM', $credential->getPublicKeyPem());
		self::assertSame('user-1', $credential->getUserId());
		self::assertSame(4, $credential->getSignatureCounter());
		self::assertSame('My phone', $credential->getName());

		// A credential that cannot be built (empty user ID) is rejected too
		$webAuthn = $this->createMock(WebAuthn::class);
		$webAuthn->method('processCreate')->willReturn($data);
		$service = new WebAuthnService('Test Site', 'example.com', $this->createStub(LoggerInterface::class), fn() => $webAuthn);
		self::assertNull($service->verifyRegistration(self::toBase64Url('client'), self::toBase64Url('attestation'), 'challenge', ''));
	}

	// ========================================
	// Authentication Methods Tests
	// ========================================

	public function testCreateAuthenticationOptions(): void
	{
		$result = $this->service->createAuthenticationOptions([self::toBase64Url("\x01\x02cred"), self::toBase64Url('other')]);
		$options = json_decode(json_encode($result->getOptions()), true);

		// Relying party and allowed credentials
		self::assertSame('example.com', $options['publicKey']['rpId']);
		self::assertSame(self::toBase64Url("\x01\x02cred"), $options['publicKey']['allowCredentials'][0]['id']);
		self::assertSame(self::toBase64Url('other'), $options['publicKey']['allowCredentials'][1]['id']);

		// The returned raw challenge is the one sent to the browser (base64url)
		self::assertNotSame('', $result->getChallenge());
		self::assertSame(self::toBase64Url($result->getChallenge()), $options['publicKey']['challenge']);

		// User verification is required
		self::assertSame('required', $options['publicKey']['userVerification']);

		// A credential ID that is not valid base64url (standard Base64 characters, garbage, empty) is refused
		foreach (['AQL/', '!!!', ''] as $invalidCredentialId) {
			try {
				$this->service->createAuthenticationOptions([$invalidCredentialId]);
				self::fail('Expected exception for the credential ID "' . $invalidCredentialId . '"');
			} catch (\InvalidArgumentException) {
				self::assertTrue(true);
			}
		}
	}

	public function testVerifyAuthentication(): void
	{
		$credential = new PasskeyCredential(self::toBase64Url('cred'), 'not a pem key', 'user', 1, 'Laptop');
		$challenge = $this->service->createAuthenticationOptions([$credential->getCredentialId()])->getChallenge();

		// Invalid data is rejected and logged, never throws
		$this->logger->expects(self::exactly(2))->method('warning');
		self::assertNull($this->service->verifyAuthentication($credential, self::toBase64Url('not json'), self::toBase64Url('authenticator data'), self::toBase64Url('signature'), $challenge));

		// Values that are not valid base64url are rejected and logged as well
		self::assertNull($this->service->verifyAuthentication($credential, 'not base64url!', self::toBase64Url('authenticator data'), self::toBase64Url('signature'), $challenge));

		// Valid authentication (WebAuthn library mocked): the browser values are decoded before reaching the library, counter and last use date are updated, the rest is kept
		$webAuthn = $this->createMock(WebAuthn::class);
		$webAuthn->expects(self::once())->method('processGet')->with('client', 'auth data', 'sig', 'not a pem key', 'challenge', 1, true)->willReturn(true);
		$webAuthn->method('getSignatureCounter')->willReturn(8);
		$service = new WebAuthnService('Test Site', 'example.com', $this->createStub(LoggerInterface::class), fn() => $webAuthn);
		$before = new \DateTimeImmutable();
		$updated = $service->verifyAuthentication($credential, self::toBase64Url('client'), self::toBase64Url('auth data'), self::toBase64Url('sig'), 'challenge');
		self::assertNotNull($updated);
		self::assertSame(8, $updated->getSignatureCounter());
		self::assertGreaterThanOrEqual($before, $updated->getLastUsedAt());
		self::assertSame('user', $updated->getUserId());
		self::assertSame('Laptop', $updated->getName());

		// The authenticator does not provide a counter: the stored one is kept
		$webAuthn = $this->createMock(WebAuthn::class);
		$webAuthn->method('processGet')->willReturn(true);
		$webAuthn->method('getSignatureCounter')->willReturn(null);
		$service = new WebAuthnService('Test Site', 'example.com', $this->createStub(LoggerInterface::class), fn() => $webAuthn);
		self::assertSame(1, $service->verifyAuthentication($credential, self::toBase64Url('client'), self::toBase64Url('auth data'), self::toBase64Url('sig'), 'challenge')?->getSignatureCounter());

		// Rejected by the library (e.g. signature counter going backwards)
		$webAuthn = $this->createMock(WebAuthn::class);
		$webAuthn->method('processGet')->willThrowException(new WebAuthnException('invalid signature'));
		$service = new WebAuthnService('Test Site', 'example.com', $this->createStub(LoggerInterface::class), fn() => $webAuthn);
		self::assertNull($service->verifyAuthentication($credential, self::toBase64Url('client'), self::toBase64Url('auth data'), self::toBase64Url('sig'), 'challenge'));
	}
}