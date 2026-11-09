<?php

namespace Osimatic\Cache;

/**
 * Stockage clé/valeur à durée de vie, avec une opération atomique « ajouter si absent ».
 *
 * Cas d'usage : empêcher le rejeu d'une requête (nonce), prendre un verrou, garantir l'idempotence d'une action.
 */
interface StoreInterface
{
	/**
	 * Enregistre la clé avec une durée de vie, uniquement si elle n'existe pas déjà.
	 *
	 * @param string $key Clé à enregistrer
	 * @param int $ttlSeconds Durée de vie de la clé, en secondes
	 * @return bool true si la clé était absente (elle vient d'être enregistrée), false si elle existait déjà
	 */
	public function addIfAbsent(string $key, int $ttlSeconds): bool;
}