<?php

namespace Osimatic\Network;

use Symfony\Component\HttpFoundation\Request;

/**
 * Protection against cross-site request forgery for state-changing requests: a request that modifies data (POST, PUT, PATCH, DELETE) must come from the same origin as the application, as told by its Origin header.
 * Safe methods (GET, HEAD, OPTIONS) are always accepted, so routes answering to them must never change data, otherwise they are left unprotected.
 * The method checked is the one really sent by the client, so a method override (X-HTTP-Method-Override header or "_method" parameter) cannot disguise a state-changing request as a safe one.
 * When the Origin header is missing, the Sec-Fetch-Site header sent by modern browsers is used instead; a state-changing request having neither is refused.
 * This complements the SameSite cookie attribute, which is not supported by every browser or client.
 *
 * The origin of the application is deduced from the request (scheme, host and port). Behind a reverse proxy, Symfony must be told which proxies to trust (Request::setTrustedProxies()), otherwise the scheme and host seen may differ from the ones of the browser (e.g. "http" instead of "https") and legitimate requests are refused.
 *
 * @link https://owasp.org/www-community/attacks/csrf Cross-Site Request Forgery
 * @link https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html OWASP CSRF Prevention Cheat Sheet
 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Origin Origin header
 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Sec-Fetch-Site Sec-Fetch-Site header
 * @link https://symfony.com/doc/current/deployment/proxies.html Symfony reverse proxies
 */
final class OriginValidator
{
	// ========================================
	// Constants
	// ========================================

	/** Methods that must not change data, and so are not checked */
	private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

	/** Values of the Sec-Fetch-Site header meaning the request has been triggered by the application itself ("same-origin") or directly by the user ("none"), as opposed to another site ("same-site", "cross-site") */
	private const array TRUSTED_FETCH_SITES = ['same-origin', 'none'];

	// ========================================
	// Public Methods
	// ========================================

	/**
	 * Tells whether a request is allowed to change data: either it uses a safe method, or its Origin header is the origin of the request itself (scheme, host and port) or one of the extra allowed origins.
	 * If the request has no Origin header, it is trusted only when its Sec-Fetch-Site header says it comes from the same origin (or directly from the user).
	 * Origins are compared ignoring case (scheme and host are case-insensitive) and a trailing slash.
	 *
	 * @param Request $request The incoming request
	 * @param string[] $extraOrigins Other allowed origins, in the form "https://example.com" without path (e.g. a local development host)
	 * @return bool True if the request is trusted
	 */
	public static function isTrustedRequest(Request $request, array $extraOrigins = []): bool
	{
		// The method really sent, not the one a client may ask for with an override header or parameter
		if (in_array($request->getRealMethod(), self::SAFE_METHODS, true)) {
			return true;
		}

		$origin = $request->headers->get('Origin');
		if (null !== $origin && '' !== $origin) {
			$origin = self::normalizeOrigin($origin);
			if ('' === $origin) {
				return false;
			}
			return $origin === self::normalizeOrigin($request->getSchemeAndHttpHost())
				|| in_array($origin, array_map(self::normalizeOrigin(...), $extraOrigins), true);
		}

		// No Origin header: rely on the Sec-Fetch-Site header, which browsers set and scripts cannot forge
		$fetchSite = $request->headers->get('Sec-Fetch-Site');
		return null !== $fetchSite && in_array(strtolower($fetchSite), self::TRUSTED_FETCH_SITES, true);
	}

	// ========================================
	// Helper Methods
	// ========================================

	/**
	 * Normalizes an origin for comparison: trimmed, lowercase, without trailing slash.
	 *
	 * @param string $origin The origin (e.g. "https://Example.com/")
	 * @return string The normalized origin (e.g. "https://example.com")
	 */
	private static function normalizeOrigin(string $origin): string
	{
		return strtolower(rtrim(trim($origin), '/'));
	}
}