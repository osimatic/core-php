<?php

namespace Osimatic\Invoice;

/**
 * The PISTE/Chorus Pro environment to target: sandbox (for testing) or production.
 * @link https://piste.gouv.fr PISTE developer portal
 */
enum ChorusProEnvironment: string
{
	case SANDBOX = 'sandbox';
	case PRODUCTION = 'production';

	/**
	 * Gets the OAuth2 token endpoint URL for this environment.
	 * Historical *.aife.economie.gouv.fr URLs were decommissioned on 2023-09-30, replaced by piste.gouv.fr.
	 * @return string
	 * @link https://piste.gouv.fr/decommissionnement-des-url-piste-historiques Historical PISTE URLs decommissioning notice
	 */
	public function getOauthUri(): string
	{
		return match ($this) {
			self::SANDBOX => 'https://sandbox-oauth.piste.gouv.fr/api/oauth/token',
			self::PRODUCTION => 'https://oauth.piste.gouv.fr/api/oauth/token',
		};
	}

	/**
	 * Gets the Chorus Pro API base URL for this environment.
	 * This exact path was only confirmed via third-party documentation, not an official *.gouv.fr source; re-verify it on the PISTE API catalog once registered, before going to production.
	 * @return string
	 */
	public function getApiBaseUri(): string
	{
		return match ($this) {
			self::SANDBOX => 'https://sandbox-api.piste.gouv.fr/cpro/factures/v1/',
			self::PRODUCTION => 'https://api.piste.gouv.fr/cpro/factures/v1/',
		};
	}
}