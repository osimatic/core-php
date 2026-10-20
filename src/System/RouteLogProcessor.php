<?php

namespace Osimatic\System;

use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Monolog processor that adds the matched route and controller of the current request to the "extra" field of each log record.
 * Records emitted without a matched route (CLI, routing not done yet, 404) are left untouched.
 *
 * Register it as a service tagged "monolog.processor", e.g. in services.yaml:
 * <code>
 *   Osimatic\System\RouteLogProcessor:
 *       tags: ['monolog.processor']
 * </code>
 *
 * @link https://symfony.com/doc/current/logging/processors.html Symfony logging processors
 */
class RouteLogProcessor
{
	// ========================================
	// Constructor
	// ========================================

	/**
	 * @param RequestStack $requestStack
	 */
	public function __construct(
		private readonly RequestStack $requestStack,
	)
	{}

	// ========================================
	// Processing
	// ========================================

	/**
	 * Adds the "route" and "controller" keys to the "extra" field of the record when a route has been matched.
	 *
	 * @param LogRecord $record
	 * @return LogRecord
	 */
	public function __invoke(LogRecord $record): LogRecord
	{
		$request = $this->requestStack->getMainRequest();
		$route = $request?->attributes->get('_route');
		if (null === $route) {
			return $record;
		}

		$record->extra['route'] = $route;
		$record->extra['controller'] = $request->attributes->get('_controller');

		return $record;
	}
}
