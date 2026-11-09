<?php

namespace Osimatic\Network;

/**
 * Utilitaires pour les en-têtes HTTP, entrants comme sortants.
 *
 * Les noms d'en-têtes sont insensibles à la casse : les méthodes de cette classe se chargent de la normalisation,
 * pour que les appelants n'aient pas à connaître la casse utilisée par le client ou par Symfony.
 */
final class HTTPHeaders
{
	/**
	 * Normalise des en-têtes : clés en minuscules, valeurs scalaires.
	 * Symfony (HeaderBag::all) renvoie des tableaux de valeurs : seule la première est conservée.
	 *
	 * @param array $headers En-têtes tels que reçus
	 * @return array<string, string|null> En-têtes normalisés
	 */
	public static function normalize(array $headers): array
	{
		$normalized = [];
		foreach ($headers as $name => $value) {
			$normalized[strtolower((string)$name)] = is_array($value) ? ($value[0] ?? null) : $value;
		}

		return $normalized;
	}

	/**
	 * Lit la valeur d'un en-tête, sans tenir compte de la casse du nom.
	 *
	 * @param array $headers En-têtes (tableau brut ou déjà normalisé)
	 * @param string $name Nom de l'en-tête recherché (ex : 'X-Signature')
	 * @return string|null La valeur, ou null si l'en-tête est absent
	 */
	public static function get(array $headers, string $name): ?string
	{
		$value = self::normalize($headers)[strtolower($name)] ?? null;

		return null === $value ? null : (string)$value;
	}
}