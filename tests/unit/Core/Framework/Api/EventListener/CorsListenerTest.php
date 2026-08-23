<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\EventListener\CorsListener;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(CorsListener::class)]
class CorsListenerTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                KernelEvents::REQUEST => ['onKernelRequest', 9999],
                KernelEvents::RESPONSE => ['onKernelResponse', 9999],
            ],
            CorsListener::getSubscribedEvents(),
        );
    }

    public function testPreflightRequestIsShortCircuited(): void
    {
        $listener = new CorsListener();
        $event = new RequestEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/api/_action/test', 'OPTIONS'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onKernelRequest($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testNonOptionsRequestIsNotShortCircuited(): void
    {
        $listener = new CorsListener();
        $event = new RequestEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/api/_action/test', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onKernelRequest($event);

        static::assertNull($event->getResponse());
    }

    public function testResponseContainsApiCorsHeaders(): void
    {
        $listener = new CorsListener();
        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/api/_action/test', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $listener->onKernelResponse($event);

        $headers = $event->getResponse()->headers;
        static::assertSame('*', $headers->get('Access-Control-Allow-Origin'));

        $allowedHeaders = explode(',', (string) $headers->get('Access-Control-Allow-Headers'));
        static::assertContains(PlatformRequest::HEADER_CONTEXT_TOKEN, $allowedHeaders);
        static::assertContains(PlatformRequest::HEADER_ACCESS_KEY, $allowedHeaders);

        $exposedHeaders = explode(',', (string) $headers->get('Access-Control-Expose-Headers'));
        static::assertContains(PlatformRequest::HEADER_CONTEXT_TOKEN, $exposedHeaders);
    }

    public function testSubRequestIsIgnored(): void
    {
        $listener = new CorsListener();
        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/api/_action/test', 'POST'),
            HttpKernelInterface::SUB_REQUEST,
            new Response(),
        );

        $listener->onKernelResponse($event);

        static::assertFalse($event->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }
}
