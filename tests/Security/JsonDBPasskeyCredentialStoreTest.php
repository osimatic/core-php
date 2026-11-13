<?php

namespace Tests\Security;

use Osimatic\Data\Encoding;
use Osimatic\Data\JsonDB;
use Osimatic\Security\JsonDBPasskeyCredentialStore;
use Osimatic\Security\PasskeyCredential;
use PHPUnit\Framework\TestCase;

class JsonDBPasskeyCredentialStoreTest extends TestCase
{
	private string $dataDirectory;
	private JsonDB $jsonDB;

	protected function setUp(): void
	{
		$this->dataDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'passkey_store_test_' . uniqid();
		mkdir($this->dataDirectory);
		$this->jsonDB = new JsonDB($this->dataDirectory);
	}

	protected function tearDown(): void
	{
		foreach (glob($this->dataDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
			@unlink($file);
		}
		@rmdir($this->dataDirectory);
	}

	/**
	 * Builds a valid credential ID (base64url) from raw bytes.
	 */
	private static function id(string $rawCredentialId): string
	{
		return Encoding::encodeBase64URL($rawCredentialId);
	}

	// ========================================
	// Constructor & Configuration Tests
	// ========================================

	public function testConstructor(): void
	{
		// Default file name
		$store = new JsonDBPasskeyCredentialStore($this->jsonDB);
		self::assertInstanceOf(JsonDBPasskeyCredentialStore::class, $store);
		self::assertTrue($store->save(new PasskeyCredential(self::id('id'), 'PEM', 'user')));
		self::assertFileExists($this->dataDirectory . DIRECTORY_SEPARATOR . JsonDBPasskeyCredentialStore::DEFAULT_FILENAME);

		// Custom file name
		$customStore = new JsonDBPasskeyCredentialStore($this->jsonDB, 'custom.json');
		self::assertTrue($customStore->save(new PasskeyCredential(self::id('id'), 'PEM', 'user')));
		self::assertFileExists($this->dataDirectory . DIRECTORY_SEPARATOR . 'custom.json');
	}

	// ========================================
	// Public Methods Tests
	// ========================================

	public function testFindAll(): void
	{
		$store = new JsonDBPasskeyCredentialStore($this->jsonDB);

		// No file yet
		self::assertSame([], $store->findAll());

		// Several credentials, binary IDs included, in insertion order
		$createdAt = new \DateTimeImmutable('2025-01-02 03:04:05');
		$lastUsedAt = new \DateTimeImmutable('2025-06-07 08:09:10');
		$store->save(new PasskeyCredential(self::id("\x00\x01\xfe\xff"), 'PEM_A', "\x00user", 3, 'My phone', $createdAt, $lastUsedAt));
		$store->save(new PasskeyCredential(self::id('second'), 'PEM_B', 'user'));
		$credentials = $store->findAll();
		self::assertCount(2, $credentials);
		self::assertSame(self::id("\x00\x01\xfe\xff"), $credentials[0]->getCredentialId());
		self::assertSame('PEM_A', $credentials[0]->getPublicKeyPem());
		self::assertSame("\x00user", $credentials[0]->getUserId());
		self::assertSame(3, $credentials[0]->getSignatureCounter());
		self::assertSame('My phone', $credentials[0]->getName());
		self::assertEquals($createdAt, $credentials[0]->getCreatedAt());
		self::assertEquals($lastUsedAt, $credentials[0]->getLastUsedAt());
		self::assertSame(self::id('second'), $credentials[1]->getCredentialId());
		self::assertNull($credentials[1]->getLastUsedAt());

		// The credential ID is stored as is (base64url), the user ID is base64-encoded
		$records = $this->jsonDB->read(JsonDBPasskeyCredentialStore::DEFAULT_FILENAME);
		self::assertSame(self::id("\x00\x01\xfe\xff"), $records[0]['credentialId']);
		self::assertSame(base64_encode("\x00user"), $records[0]['userId']);

		// Invalid records are skipped
		$this->jsonDB->write(JsonDBPasskeyCredentialStore::DEFAULT_FILENAME, [
			['credentialId' => '!!!not-base64!!!', 'publicKeyPem' => 'PEM', 'userId' => base64_encode('user')],
			['credentialId' => base64_encode("\xfb\xff"), 'publicKeyPem' => 'PEM', 'userId' => base64_encode('user')], // standard Base64 ("+/" characters and padding), not base64url
			['credentialId' => self::id('ok'), 'publicKeyPem' => '', 'userId' => base64_encode('user')],
			['credentialId' => self::id('no-user'), 'publicKeyPem' => 'PEM'],
			['credentialId' => self::id('bad-date'), 'publicKeyPem' => 'PEM', 'userId' => base64_encode('user'), 'createdAt' => 'not a date'],
			['credentialId' => self::id('valid'), 'publicKeyPem' => 'PEM', 'userId' => base64_encode('user'), 'signatureCounter' => 9],
			'not-an-array',
		]);
		$credentials = $store->findAll();
		self::assertCount(1, $credentials);
		self::assertSame(self::id('valid'), $credentials[0]->getCredentialId());
		self::assertSame(9, $credentials[0]->getSignatureCounter());

		// Unreadable file (invalid JSON) gives an empty list
		file_put_contents($this->dataDirectory . DIRECTORY_SEPARATOR . JsonDBPasskeyCredentialStore::DEFAULT_FILENAME, '{not json');
		self::assertSame([], $store->findAll());
	}

	public function testFindByUserId(): void
	{
		$store = new JsonDBPasskeyCredentialStore($this->jsonDB);

		// No file yet
		self::assertSame([], $store->findByUserId('alice'));

		$store->save(new PasskeyCredential(self::id('a1'), 'PEM', 'alice'));
		$store->save(new PasskeyCredential(self::id('b1'), 'PEM', 'bob'));
		$store->save(new PasskeyCredential(self::id('a2'), 'PEM', 'alice'));

		// Only the credentials of the given user, in insertion order
		$credentials = $store->findByUserId('alice');
		self::assertCount(2, $credentials);
		self::assertSame(self::id('a1'), $credentials[0]->getCredentialId());
		self::assertSame(self::id('a2'), $credentials[1]->getCredentialId());
		self::assertCount(1, $store->findByUserId('bob'));

		// Unknown user
		self::assertSame([], $store->findByUserId('carol'));
		self::assertSame([], $store->findByUserId(''));
	}

	public function testFindByCredentialId(): void
	{
		$store = new JsonDBPasskeyCredentialStore($this->jsonDB);
		$store->save(new PasskeyCredential(self::id("\x00\x01\xfe"), 'PEM_A', 'user'));
		$store->save(new PasskeyCredential(self::id('other'), 'PEM_B', 'user'));

		// Found, with the ID exactly as the browser sends it
		$credential = $store->findByCredentialId(self::id("\x00\x01\xfe"));
		self::assertNotNull($credential);
		self::assertSame('PEM_A', $credential->getPublicKeyPem());

		// Not found
		self::assertNull($store->findByCredentialId(self::id('unknown')));
		self::assertNull($store->findByCredentialId(''));

		// Not found when written in another form than the canonical one (padding, standard Base64 characters)
		self::assertNull($store->findByCredentialId(self::id("\x00\x01\xfe") . '='));
		self::assertNull($store->findByCredentialId(base64_encode("\x00\x01\xfe")));
	}

	public function testSave(): void
	{
		$store = new JsonDBPasskeyCredentialStore($this->jsonDB);

		// Insert
		self::assertTrue($store->save(new PasskeyCredential(self::id('first'), 'PEM_A', 'user', 1)));
		self::assertTrue($store->save(new PasskeyCredential(self::id('second'), 'PEM_B', 'user', 2)));
		self::assertCount(2, $store->findAll());

		// Replace an existing credential (same ID), without changing the order nor adding a record
		self::assertTrue($store->save(new PasskeyCredential(self::id('first'), 'PEM_A', 'user', 10)));
		$credentials = $store->findAll();
		self::assertCount(2, $credentials);
		self::assertSame(self::id('first'), $credentials[0]->getCredentialId());
		self::assertSame(10, $credentials[0]->getSignatureCounter());
		self::assertSame(2, $credentials[1]->getSignatureCounter());

		// An unreadable file is never overwritten: the existing content must not be lost
		$filepath = $this->dataDirectory . DIRECTORY_SEPARATOR . JsonDBPasskeyCredentialStore::DEFAULT_FILENAME;
		file_put_contents($filepath, '{not json');
		self::assertFalse($store->save(new PasskeyCredential(self::id('third'), 'PEM_C', 'user')));
		self::assertSame('{not json', file_get_contents($filepath));

		// Records that cannot be turned into credentials are kept when saving another one
		$this->jsonDB->write(JsonDBPasskeyCredentialStore::DEFAULT_FILENAME, [['credentialId' => '!!!not-base64!!!', 'publicKeyPem' => 'PEM']]);
		self::assertTrue($store->save(new PasskeyCredential(self::id('fourth'), 'PEM_D', 'user')));
		self::assertCount(2, $this->jsonDB->read(JsonDBPasskeyCredentialStore::DEFAULT_FILENAME));

		// Failure: the data directory cannot be written to
		$brokenDirectory = $this->dataDirectory . DIRECTORY_SEPARATOR . 'not_a_directory';
		file_put_contents($brokenDirectory, 'x');
		$brokenStore = new JsonDBPasskeyCredentialStore(new JsonDB($brokenDirectory));
		self::assertFalse($brokenStore->save(new PasskeyCredential(self::id('id'), 'PEM', 'user')));
	}

	public function testDelete(): void
	{
		$store = new JsonDBPasskeyCredentialStore($this->jsonDB);

		// No file yet
		self::assertFalse($store->delete(self::id('first')));

		$store->save(new PasskeyCredential(self::id('first'), 'PEM_A', 'user'));
		$store->save(new PasskeyCredential(self::id("\x00second"), 'PEM_B', 'user'));
		$store->save(new PasskeyCredential(self::id('third'), 'PEM_C', 'user'));

		// Delete an existing credential, the others are kept in order
		self::assertTrue($store->delete(self::id("\x00second")));
		self::assertNull($store->findByCredentialId(self::id("\x00second")));
		$credentials = $store->findAll();
		self::assertCount(2, $credentials);
		self::assertSame(self::id('first'), $credentials[0]->getCredentialId());
		self::assertSame(self::id('third'), $credentials[1]->getCredentialId());

		// Already deleted or unknown
		self::assertFalse($store->delete(self::id("\x00second")));
		self::assertFalse($store->delete(self::id('unknown')));
		self::assertCount(2, $store->findAll());

		// An unreadable file is left untouched
		$filepath = $this->dataDirectory . DIRECTORY_SEPARATOR . JsonDBPasskeyCredentialStore::DEFAULT_FILENAME;
		file_put_contents($filepath, '{not json');
		self::assertFalse($store->delete(self::id('first')));
		self::assertSame('{not json', file_get_contents($filepath));
	}
}