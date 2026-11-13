<?php

namespace Osimatic\Security;

/**
 * Result of the first step of a passkey registration or authentication.
 * It gathers the options to send to the browser and the raw challenge the server must keep until the browser answers.
 *
 * @link https://www.w3.org/TR/webauthn-3/#sctn-cryptographic-challenges WebAuthn cryptographic challenges
 */
final class PasskeyCeremonyOptions
{
	// ========================================
	// Constructor & Configuration
	// ========================================

	/**
	 * @param \stdClass $options The options to send to the browser as JSON
	 * @param string $challenge The raw challenge (binary string) to keep until verification
	 */
	public function __construct(
		private readonly \stdClass $options,
		private readonly string $challenge,
	) {}

	// ========================================
	// Public Methods
	// ========================================

	/**
	 * Returns the options to send to the browser (argument of navigator.credentials.create() or navigator.credentials.get()).
	 *
	 * @return \stdClass The options
	 */
	public function getOptions(): \stdClass
	{
		return $this->options;
	}

	/**
	 * Returns the raw challenge (binary string) to keep (e.g. in the session) until the browser answers, then to discard whatever the outcome.
	 *
	 * @return string The challenge
	 */
	public function getChallenge(): string
	{
		return $this->challenge;
	}

	/**
	 * Returns the options encoded as JSON, ready to be sent to the browser.
	 *
	 * @return string The JSON string
	 * @throws \JsonException If the options cannot be encoded
	 */
	public function toJson(): string
	{
		return json_encode($this->options, JSON_THROW_ON_ERROR);
	}
}
