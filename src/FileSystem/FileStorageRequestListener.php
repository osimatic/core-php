<?php

namespace Osimatic\FileSystem;

use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Symfony event listener that registers the configured file storage as the default one,
 * so that code with no access to dependency injection (e.g. Doctrine entities) can resolve public file URLs.
 *
 * Register it as a service tagged for the "kernel.request" event, e.g. in services.yaml:
 * <code>
 *   Osimatic\FileSystem\FileStorageRequestListener:
 *       tags:
 *           - { name: 'kernel.event_listener', event: 'kernel.request', method: 'onKernelRequest', priority: 100 }
 * </code>
 *
 * @link https://symfony.com/doc/current/reference/events.html#kernel-request Symfony kernel.request event
 */
class FileStorageRequestListener
{
	// ========================================
	// Constructor
	// ========================================

	public function __construct(
		private readonly FileStorageInterface $fileStorage,
	)
	{}

	// ========================================
	// Event Handling
	// ========================================

	/**
	 * Registers the configured file storage as the default one.
	 * @param RequestEvent $event
	 */
	public function onKernelRequest(RequestEvent $event): void
	{
		DefaultFileStorage::set($this->fileStorage);
	}
}