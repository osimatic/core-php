<?php

namespace Osimatic\Network;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Symfony event listener that decodes JSON request bodies into the request parameter bag,
 * so that controllers can read JSON payloads the same way as form-encoded ones (via $request->get()).
 *
 * Register it as a service tagged for the "kernel.request" event, e.g. in services.yaml:
 * <code>
 *   Osimatic\Network\JsonRequestTransformerListener:
 *       tags:
 *           - { name: 'kernel.event_listener', event: 'kernel.request', method: 'onKernelRequest', priority: 100 }
 * </code>
 *
 * @link https://symfony.com/doc/current/reference/events.html#kernel-request Symfony kernel.request event
 */
class JsonRequestTransformerListener
{
	// ========================================
	// Constants
	// ========================================

	/**
	 * Default request format names (as resolved by Request::getContentTypeFormat()) handled by this listener.
	 */
	public const array DEFAULT_CONTENT_TYPES = ['json', 'jsonld'];

	// ========================================
	// Constructor
	// ========================================

	/**
	 * @param string[] $contentTypes Request format names (e.g. "json", "jsonld") whose body must be decoded
	 */
	public function __construct(
		private readonly array $contentTypes = self::DEFAULT_CONTENT_TYPES,
	)
	{}

	// ========================================
	// Event Handling
	// ========================================

	/**
	 * Decodes the JSON body of the request and merges it into the request parameter bag.
	 * Responds with a 400 Bad Request if the body is not valid JSON.
	 *
	 * @param RequestEvent $event
	 */
	public function onKernelRequest(RequestEvent $event): void
	{
		$request = $event->getRequest();

		if (!$this->supports($request)) {
			return;
		}

		try {
			$data = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);

			if (is_array($data)) {
				$request->request->replace($data);
			}
		} catch (\JsonException $exception) {
			$event->setResponse(new JsonResponse(['message' => $exception->getMessage()], Response::HTTP_BAD_REQUEST));
		}
	}

	// ========================================
	// Helper Methods
	// ========================================

	/**
	 * Checks whether the request's content type matches one of the configured formats and has a non-empty body.
	 *
	 * @param Request $request
	 * @return bool
	 */
	private function supports(Request $request): bool
	{
		return in_array($request->getContentTypeFormat(), $this->contentTypes, true) && $request->getContent();
	}
}