<?php

namespace Osimatic\Cache;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Implémentation de StoreInterface s'appuyant sur un pool de cache PSR-6 (ex. cache.app de Symfony).
 *
 * Attention : l'opération n'est PAS atomique. Deux appels simultanés avec la même clé peuvent tous deux réussir.
 * Suffisant pour un seul serveur à faible trafic ; pour une garantie stricte, utiliser RedisStore.
 *
 * La clé est hachée : les pools PSR-6 rejettent les caractères réservés ({}()/\@:).
 */
final class CacheStore implements StoreInterface
{
	public function __construct(
		private readonly CacheItemPoolInterface $pool,
	)
	{}

	public function addIfAbsent(string $key, int $ttlSeconds): bool
	{
		$item = $this->pool->getItem(hash('sha256', $key));
		if ($item->isHit()) {
			return false;
		}

		$item->set(1)->expiresAfter($ttlSeconds);

		return $this->pool->save($item);
	}

	public function has(string $key): bool
	{
		return $this->pool->getItem(hash('sha256', $key))->isHit();
	}
}