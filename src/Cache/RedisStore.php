<?php

namespace Osimatic\Cache;

/**
 * Implémentation de StoreInterface s'appuyant sur Redis (extension phpredis).
 *
 * L'opération est atomique : SET key 1 NX EX ttl enregistre la clé seulement si elle est absente, en une seule commande.
 */
final class RedisStore implements StoreInterface
{
	public function __construct(
		private readonly \Redis $redis,
		private readonly string $prefix = '',
	)
	{}

	public function addIfAbsent(string $key, int $ttlSeconds): bool
	{
		return true === $this->redis->set($this->prefix . $key, 1, ['nx', 'ex' => $ttlSeconds]);
	}
}