<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\EventListener;

use Contena\Core\Framework\Api\Cors\CorsHeaderProviderInterface;
use Contena\Core\Framework\Api\Cors\CorsHeaders;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
class CorsListener implements EventSubscriberInterface
{
    /**
     * @param iterable<CorsHeaderProviderInterface> $headerProviders
     */
    public function __construct(private readonly iterable $headerProviders)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 9999],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $method = $event->getRequest()->getRealMethod();

        if ($method === 'OPTIONS') {
            $response = new Response();
            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = new CorsHeaders();

        foreach ($this->headerProviders as $provider) {
            $provider->provide($headers);
        }

        $response = $event->getResponse();
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE');
        $response->headers->set('Access-Control-Allow-Headers', implode(',', $headers->getAllowed()));
        $response->headers->set('Access-Control-Expose-Headers', implode(',', $headers->getExposed()));
    }
}
