<?php

namespace Osimatic\Security;

/**
 * Persistence contract for registered passkeys.
 * Implementations decide where the credentials are kept (JSON file, database, etc.).
 */
interface PasskeyCredentialStoreInterface
{
	/**
	 * Returns all registered credentials, whatever their owner.
	 *
	 * @return PasskeyCredential[] The registered credentials (empty array if none)
	 */
	public function findAll(): array;

	/**
	 * Returns the credentials registered by a user.
	 * Use it to build the list of allowed credentials of an authentication, or the list of passkeys displayed in the account page.
	 *
	 * @param string $userId Identifier of the user
	 * @return PasskeyCredential[] The credentials of the user (empty array if none)
	 */
	public function findByUserId(string $userId): array;

	/**
	 * Finds a registered credential by its raw credential ID.
	 *
	 * @param string $credentialId Raw credential ID (binary string)
	 * @return PasskeyCredential|null The credential if found, null otherwise
	 */
	public function findByCredentialId(string $credentialId): ?PasskeyCredential;

	/**
	 * Saves a credential: inserts it, or replaces the existing one having the same credential ID (e.g. to update the signature counter).
	 *
	 * @param PasskeyCredential $credential The credential to save
	 * @return bool True if the credential has been saved, false otherwise
	 */
	public function save(PasskeyCredential $credential): bool;

	/**
	 * Deletes a credential, e.g. when a user revokes a lost authenticator.
	 *
	 * @param string $credentialId Raw credential ID (binary string)
	 * @return bool True if the credential has been deleted, false if it does not exist or could not be deleted
	 */
	public function delete(string $credentialId): bool;
}
