<?php

namespace Osimatic\Security;

/**
 * Immutable record of a registered passkey (WebAuthn credential), as kept by the server.
 * It only holds public data: the credential ID and the public key. The private key never leaves the authenticator device, so this object is not secret.
 * A credential belongs to a user, and carries a few metadata (name, creation and last use dates) so that users can manage their passkeys.
 *
 * @link https://www.w3.org/TR/webauthn-3/#credential-id WebAuthn credential ID
 * @link https://www.w3.org/TR/webauthn-3/#signature-counter WebAuthn signature counter
 */
final class PasskeyCredential
{
	// ========================================
	// Properties
	// ========================================

	/** Raw credential ID as returned by the authenticator (binary string) */
	private readonly string $credentialId;

	/** Public key in PEM format, used to verify assertion signatures */
	private readonly string $publicKeyPem;

	/** Identifier of the user owning this credential (the same value as the user ID given when registering) */
	private readonly string $userId;

	/** Last signature counter seen for this credential, used to detect cloned authenticators */
	private readonly int $signatureCounter;

	/** Label given to this passkey, to let the user tell his passkeys apart (empty if none) */
	private readonly string $name;

	/** Date of registration of this credential */
	private readonly \DateTimeImmutable $createdAt;

	/** Date of the last successful authentication with this credential (null if never used since registration) */
	private readonly ?\DateTimeImmutable $lastUsedAt;

	// ========================================
	// Constructor & Configuration
	// ========================================

	/**
	 * @param string $credentialId Raw credential ID (binary string), must not be empty
	 * @param string $publicKeyPem Public key in PEM format, must not be empty
	 * @param string $userId Identifier of the user owning this credential, must not be empty
	 * @param int $signatureCounter Last signature counter seen for this credential (0 if the authenticator does not implement it)
	 * @param string $name Label of the passkey (empty if none)
	 * @param \DateTimeImmutable|null $createdAt Date of registration (default: now)
	 * @param \DateTimeImmutable|null $lastUsedAt Date of the last successful authentication (default: null, never used)
	 * @throws \InvalidArgumentException If the credential ID, the public key or the user ID is empty, or if the signature counter is negative
	 */
	public function __construct(string $credentialId, string $publicKeyPem, string $userId, int $signatureCounter = 0, string $name = '', ?\DateTimeImmutable $createdAt = null, ?\DateTimeImmutable $lastUsedAt = null)
	{
		if ('' === $credentialId) {
			throw new \InvalidArgumentException('The credential ID must not be empty.');
		}
		if ('' === $publicKeyPem) {
			throw new \InvalidArgumentException('The public key must not be empty.');
		}
		if ('' === $userId) {
			throw new \InvalidArgumentException('The user ID must not be empty.');
		}
		if ($signatureCounter < 0) {
			throw new \InvalidArgumentException('The signature counter must not be negative.');
		}

		$this->credentialId = $credentialId;
		$this->publicKeyPem = $publicKeyPem;
		$this->userId = $userId;
		$this->signatureCounter = $signatureCounter;
		$this->name = $name;
		$this->createdAt = $createdAt ?? new \DateTimeImmutable();
		$this->lastUsedAt = $lastUsedAt;
	}

	// ========================================
	// Public Methods
	// ========================================

	/**
	 * Returns the raw credential ID (binary string).
	 *
	 * @return string The credential ID
	 */
	public function getCredentialId(): string
	{
		return $this->credentialId;
	}

	/**
	 * Returns the public key in PEM format.
	 *
	 * @return string The public key
	 */
	public function getPublicKeyPem(): string
	{
		return $this->publicKeyPem;
	}

	/**
	 * Returns the identifier of the user owning this credential.
	 *
	 * @return string The user ID
	 */
	public function getUserId(): string
	{
		return $this->userId;
	}

	/**
	 * Returns the last signature counter seen for this credential.
	 *
	 * @return int The signature counter
	 */
	public function getSignatureCounter(): int
	{
		return $this->signatureCounter;
	}

	/**
	 * Returns the label of the passkey.
	 *
	 * @return string The name (empty if none)
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Returns the date of registration of this credential.
	 *
	 * @return \DateTimeImmutable The creation date
	 */
	public function getCreatedAt(): \DateTimeImmutable
	{
		return $this->createdAt;
	}

	/**
	 * Returns the date of the last successful authentication with this credential.
	 *
	 * @return \DateTimeImmutable|null The last use date, null if the credential has never been used since registration
	 */
	public function getLastUsedAt(): ?\DateTimeImmutable
	{
		return $this->lastUsedAt;
	}

	/**
	 * Returns a copy of this credential with an updated signature counter.
	 * The original instance is not modified.
	 *
	 * @param int $signatureCounter The new signature counter
	 * @return self A new instance with the given signature counter
	 * @throws \InvalidArgumentException If the signature counter is negative
	 */
	public function withSignatureCounter(int $signatureCounter): self
	{
		return new self($this->credentialId, $this->publicKeyPem, $this->userId, $signatureCounter, $this->name, $this->createdAt, $this->lastUsedAt);
	}

	/**
	 * Returns a copy of this credential with another label.
	 * The original instance is not modified.
	 *
	 * @param string $name The new name
	 * @return self A new instance with the given name
	 */
	public function withName(string $name): self
	{
		return new self($this->credentialId, $this->publicKeyPem, $this->userId, $this->signatureCounter, $name, $this->createdAt, $this->lastUsedAt);
	}

	/**
	 * Returns a copy of this credential with an updated last use date.
	 * The original instance is not modified.
	 *
	 * @param \DateTimeImmutable $lastUsedAt The new last use date
	 * @return self A new instance with the given last use date
	 */
	public function withLastUsedAt(\DateTimeImmutable $lastUsedAt): self
	{
		return new self($this->credentialId, $this->publicKeyPem, $this->userId, $this->signatureCounter, $this->name, $this->createdAt, $lastUsedAt);
	}
}
