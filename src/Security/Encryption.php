<?php

namespace Osimatic\Security;

/**
 * Utility class for symmetric encryption and decryption using OpenSSL.
 * A random initialization vector (IV) is generated on encryption and prepended
 * to the ciphertext, so the caller does not need to manage or store it separately.
 * @link https://en.wikipedia.org/wiki/Initialization_vector Initialization vector
 * @link https://www.php.net/manual/en/function.openssl-encrypt.php openssl_encrypt
 */
class Encryption
{
	/**
	 * Encrypts data with a random IV, prepended to the returned ciphertext.
	 * @param string $data The plaintext data to encrypt
	 * @param string $key The secret encryption key
	 * @param string $cipher The OpenSSL cipher method to use (default: aes-256-cbc)
	 * @return string Base64-encoded IV followed by the ciphertext
	 */
	public static function encrypt(string $data, string $key, string $cipher = 'aes-256-cbc'): string
	{
		$iv = random_bytes(openssl_cipher_iv_length($cipher));
		$encryptedData = openssl_encrypt($data, $cipher, $key, 0, $iv);

		return base64_encode($iv.$encryptedData);
	}

	/**
	 * Decrypts data previously encrypted with self::encrypt().
	 * @param string $encryptedData Base64-encoded IV followed by the ciphertext, as returned by self::encrypt()
	 * @param string $key The secret encryption key
	 * @param string $cipher The OpenSSL cipher method used for encryption (default: aes-256-cbc)
	 * @return string|false The decrypted plaintext, or false on failure
	 */
	public static function decrypt(string $encryptedData, string $key, string $cipher = 'aes-256-cbc'): string|false
	{
		$rawData = base64_decode($encryptedData);
		$ivLength = openssl_cipher_iv_length($cipher);
		$iv = substr($rawData, 0, $ivLength);
		$cipherText = substr($rawData, $ivLength);

		return openssl_decrypt($cipherText, $cipher, $key, 0, $iv);
	}
}