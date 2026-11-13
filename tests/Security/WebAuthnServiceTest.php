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
		$result = $this->service->createRegistrationOptions("\x01\x02user", 'admin', 'Administrator', ["\x09\x08cred"]);
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
	}

	public function testVerifyRegistration(): void
	{
		$challenge = $this->service->createRegistrationOptions('user', 'admin', 'Administrator')->getChallenge();

		// Invalid data is rejected and logged, never throws
		$this->logger->expects(self::once())->method('warning');
		self::assertNull($this->service->verifyRegistration('not json', 'not cbor', $challenge, 'user'));

		// Valid registration (WebAuthn library mocked): the credential is bound to the user and carries the name
		$data = (object)['credentialId' => "\x01cred", 'credentialPublicKey' => 'PEM', 'signatureCounter' => 4];
		$webAuthn = $this->createMock(WebAuthn::class);
		$webAuthn->expects(self::once())->method('processCreate')->with('client', 'attestation', 'challenge', true)->willReturn($data);
		$service = new WebAuthnService('Test Site', 'example.com', $this->logger, fn() => $webAuthn);
		$credential = $service->verifyRegistration('client', 'attestation', 'challenge', 'user-1', 'My phone');
		self::assertNotNull($credential);
		self::assertSame("\x01cred", $credential->getCredentialId());
		self::assertSame('PEM', $credential->getPublicKeyPem());
		self::assertSame('user-1', $credential->getUserId());
		self::assertSame(4, $credential->getSignatureCounter());
		self::assertSame('My phone', $credential->getName());

		// A credential that cannot be built (empty user ID) is rejected too
		$webAuthn = $this->createMock(WebAuthn::class);
		$webAuthn->method('processCreate')->willReturn($data);
		$service = new WebAuthnService('Test Site', 'example.com', $this->createStub(LoggerInterface::class), fn() => $webAuthn);
		self::assertNull($service->verifyRegistration('client', 'attestation', 'challenge', ''));
	}

	// ========================================
	// Authentication Methods Tests
	// ========================================

	public function testCreateAuthenticationOptions(): void
	{
		$result = $this->service->createAuthenticationOptions(["\x01\x02cred", 'other']);
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
	}

	public function testVerifyAuthentication(): void
	{
		$credential = new PasskeyCredential('cred', 'not a pem key', 'user', 1, 'Laptop');
		$challenge = $this->service->createAuthenticationOptions(['cred'])->getChallenge();

		// Invalid data is rejected and logged, never throws
		$this->logger->expects(self::once())->method('warning');
		self::assertNull($this->service->verifyAuthentication($credential, 'not json', 'authenticator data', 'signature', $challenge));

		// Valid authentication (WebAuthn library mocked): counter and last use date are updated, the rest is kept
		$webAuthn = $this->createMock(WebAuthn::class);
		$webAuthn->expects(self::once())->method('processGet')->with('client', 'auth data', 'sig', 'not a pem key', 'challenge', 1, true)->willReturn(true);
		$webAuthn->method('getSignatureCounter')->willReturn(8);
		$service = new WebAuthnService('Test Site', 'example.com', $this->createStub(LoggerInterface::class), fn() => $webAuthn);
		$before = new \DateTimeImmutable();
		$updated = $service->verifyAuthentication($credential, 'client', 'auth data', 'sig', 'challenge');
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
		self::assertSame(1, $service->verifyAuthentication($credential, 'client', 'auth data', 'sig', 'challenge')?->getSignatureCounter());

		// Rejected by the library (e.g. signature counter going backwards)
		$webAuthn = $this->createMock(WebAuthn::class);
		$webAuthn->method('processGet')->willThrowException(new WebAuthnException('invalid signature'));
		$service = new WebAuthnService('Test Site', 'example.com', $this->createStub(LoggerInterface::class), fn() => $webAuthn);
		self::assertNull($service->verifyAuthentication($credential, 'client', 'auth data', 'sig', 'challenge'));
	}
}