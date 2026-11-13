<?php

namespace Osimatic\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * Login state kept in the PHP session, and single-use storage of the challenge of a passkey ceremony in progress.
 * The login state does not depend on the authentication method. The session cookie is HttpOnly, SameSite=Strict, and Secure when the request is served over HTTPS. Its path is the base URL of the request.
 *
 * The instance is shared through a singleton, configured once with initialize() before the first call to getInstance().
 *
 * @link https://www.php.net/manual/en/book.session.php PHP sessions
 * @link https://owasp.org/www-community/attacks/Session_fixation Session fixation
 */
final class LoginSession
{
	// ========================================
	// Constants
	// ========================================

	/** Default name of the session cookie */
	public const string DEFAULT_COOKIE_NAME = 'login_session';

	/** Default maximum duration (in seconds) of a login (8 hours) */
	public const int DEFAULT_MAX_LOGIN_LIFETIME_SECONDS = 28800;

	/** Default maximum age (in seconds) of a challenge */
	public const int DEFAULT_CHALLENGE_LIFETIME_SECONDS = 120;

	/** Session key holding the time of the login */
	private const string KEY_AUTHENTICATED_AT = 'authenticated_at';

	/** Session key holding the logged-in user */
	private const string KEY_USER = 'user';

	/** Session key holding the challenge of the ceremony in progress */
	private const string KEY_CHALLENGE = 'passkey_challenge';

	// ========================================
	// Properties
	// ========================================

	/** @var self|null Singleton instance */
	private static ?self $instance = null;

	/** @var array{0: string, 1: int, 2: int}|null Configuration given to initialize(), used when the instance is created */
	private static ?array $configuration = null;

	// ========================================
	// Constructor & Singleton
	// ========================================

	/**
	 * @param string $cookieName Name of the session cookie
	 * @param int $maxLoginLifetimeSeconds Duration of a login, after which the user has to authenticate again
	 * @param int $challengeLifetimeSeconds Maximum age of a challenge
	 */
	private function __construct(
		private readonly string $cookieName,
		private readonly int $maxLoginLifetimeSeconds,
		private readonly int $challengeLifetimeSeconds,
	) {}

	/**
	 * Sets the configuration used by the singleton. Must be called before the first call to getInstance(); later calls have no effect.
	 *
	 * @param string $cookieName Name of the session cookie
	 * @param int $maxLoginLifetimeSeconds Duration of a login, after which the user has to authenticate again
	 * @param int $challengeLifetimeSeconds Maximum age of a challenge
	 * @throws \InvalidArgumentException If a duration is not positive
	 */
	public static function initialize(
		string $cookieName = self::DEFAULT_COOKIE_NAME,
		int $maxLoginLifetimeSeconds = self::DEFAULT_MAX_LOGIN_LIFETIME_SECONDS,
		int $challengeLifetimeSeconds = self::DEFAULT_CHALLENGE_LIFETIME_SECONDS,
	): void
	{
		if ($maxLoginLifetimeSeconds <= 0 || $challengeLifetimeSeconds <= 0) {
			throw new \InvalidArgumentException('Lifetimes must be positive.');
		}
		if (null === self::$instance) {
			self::$configuration = [$cookieName, $maxLoginLifetimeSeconds, $challengeLifetimeSeconds];
		}
	}

	/**
	 * Returns the login session shared by the whole request. If initialize() has not been called, the default configuration is used.
	 *
	 * @return self The singleton instance
	 * @link https://en.wikipedia.org/wiki/Singleton_pattern Singleton pattern
	 */
	public static function getInstance(): self
	{
		if (null === self::$instance) {
			[$cookieName, $maxLoginLifetime, $challengeLifetime] = self::$configuration
				?? [self::DEFAULT_COOKIE_NAME, self::DEFAULT_MAX_LOGIN_LIFETIME_SECONDS, self::DEFAULT_CHALLENGE_LIFETIME_SECONDS];
			self::$instance = new self($cookieName, $maxLoginLifetime, $challengeLifetime);
		}
		return self::$instance;
	}

	// ========================================
	// Public Methods
	// ========================================

	/**
	 * Starts the session. Must be called once per request, before any other method. Does nothing if the session is already started.
	 *
	 * @param Request $request The current request (used for the cookie path and to detect HTTPS)
	 */
	public function start(Request $request): void
	{
		if (PHP_SESSION_ACTIVE === session_status()) {
			return;
		}

		session_name($this->cookieName);
		session_set_cookie_params([
			'lifetime' => 0,
			'path' => '' !== $request->getBaseUrl() ? $request->getBaseUrl() : '/',
			'secure' => $request->isSecure(),
			'httponly' => true,
			'samesite' => 'Strict',
		]);
		session_start();
	}

	/**
	 * Tells whether the current session has a login that has not expired.
	 *
	 * @return bool True if the user is logged in
	 */
	public function isAuthenticated(): bool
	{
		$authenticatedAt = $_SESSION[self::KEY_AUTHENTICATED_AT] ?? null;
		return is_int($authenticatedAt) && (time() - $authenticatedAt) < $this->maxLoginLifetimeSeconds;
	}

	/**
	 * Marks the session as logged in for the given user. The session ID is renewed to prevent session fixation.
	 *
	 * @param string $email E-mail address of the user, used as login
	 * @param string $firstName First name of the user, for display
	 * @param string $lastName Last name of the user, for display
	 */
	public function login(string $email, string $firstName = '', string $lastName = ''): void
	{
		session_regenerate_id(true);
		$_SESSION[self::KEY_AUTHENTICATED_AT] = time();
		$_SESSION[self::KEY_USER] = ['email' => $email, 'firstName' => $firstName, 'lastName' => $lastName];
	}

	/**
	 * Returns the logged-in user, or null if nobody is logged in.
	 *
	 * @return array{email: string, firstName: string, lastName: string}|null The user given to login(), or null
	 */
	public function getUser(): ?array
	{
		return $this->isAuthenticated() ? ($_SESSION[self::KEY_USER] ?? null) : null;
	}

	/**
	 * Ends the session and removes its cookie.
	 */
	public function logout(): void
	{
		$_SESSION = [];
		if (PHP_SESSION_ACTIVE === session_status()) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', [
				'expires' => time() - 3600,
				'path' => $params['path'],
				'secure' => $params['secure'],
				'httponly' => true,
				'samesite' => 'Strict',
			]);
			session_destroy();
		}
	}

	/**
	 * Keeps the challenge of a ceremony until the browser answers. Replaces any challenge kept before.
	 *
	 * @param string $ceremony Kind of ceremony ("registration" or "authentication")
	 * @param string $challenge The raw challenge (binary string)
	 * @param array<string, mixed> $context Data to retrieve together with the challenge when the browser answers
	 */
	public function storeChallenge(string $ceremony, string $challenge, array $context = []): void
	{
		$_SESSION[self::KEY_CHALLENGE] = [
			'ceremony' => $ceremony,
			'challenge' => base64_encode($challenge),
			'context' => $context,
			'expiresAt' => time() + $this->challengeLifetimeSeconds,
		];
	}

	/**
	 * Retrieves and discards the challenge kept for a ceremony. The challenge is single-use: it is discarded whatever the outcome of the ceremony.
	 *
	 * @param string $ceremony Kind of ceremony expected ("registration" or "authentication")
	 * @return array{challenge: string, context: array<string, mixed>}|null The raw challenge and its context, or null if there is none, if it has expired or if it was kept for another kind of ceremony
	 */
	public function pullChallenge(string $ceremony): ?array
	{
		$stored = $_SESSION[self::KEY_CHALLENGE] ?? null;
		unset($_SESSION[self::KEY_CHALLENGE]);

		if (!is_array($stored) || ($stored['ceremony'] ?? null) !== $ceremony || ($stored['expiresAt'] ?? 0) < time()) {
			return null;
		}
		$challenge = base64_decode((string)($stored['challenge'] ?? ''), true);
		if (false === $challenge || '' === $challenge) {
			return null;
		}
		return ['challenge' => $challenge, 'context' => is_array($stored['context'] ?? null) ? $stored['context'] : []];
	}
}