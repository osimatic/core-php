<?php

namespace Osimatic\Location;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Symfony event listener that detects the request locale (from the Accept-Language header)
 * and applies it via Locale::setFromRequest().
 *
 * Register it as a service tagged for the "kernel.request" event, e.g. in services.yaml:
 * <code>
 *   Osimatic\Location\LocaleListener:
 *       arguments:
 *           $defaultLocale: '%kernel.default_locale%'
 *       tags:
 *           - { name: 'kernel.event_listener', event: 'kernel.request', method: 'onKernelRequest', priority: 17 }
 * </code>
 *
 * @link https://symfony.com/doc/current/reference/events.html#kernel-request Symfony kernel.request event
 */
class LocaleListener
{
	// ========================================
	// Constructor
	// ========================================

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly string $defaultLocale,
	)
	{}

	// ========================================
	// Event Handling
	// ========================================

	/**
	 * Detects and applies the request locale.
	 * @param RequestEvent $event
	 */
	public function onKernelRequest(RequestEvent $event): void
	{
		$locale = Locale::setFromRequest($event->getRequest(), $this->defaultLocale);

		$this->logger->info('Configuration Locale.', ['locale' => $locale]);
	}
}