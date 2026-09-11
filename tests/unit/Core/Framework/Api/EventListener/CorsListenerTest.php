<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\EventListener;

use Contena\Core\Framework\Api\EventListener\CorsListener;
use Contena\Core\Framework\Api\Cors\CorsHeaderProviderInterface;
use Contena\Core\PlatformRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

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

    public function testDefaultHeadersIncludeChannelAndMcpHeaders(): void
    {
        $headers = $this->dispatchResponse(new CorsListener());

        static::assertStringContainsString(PlatformRequest::HEADER_INCLUDE_SEO_URLS, (string) $headers->get('Access-Control-Allow-Headers'));
        static::assertStringContainsString(PlatformRequest::HEADER_MCP_SESSION_ID, (string) $headers->get('Access-Control-Allow-Headers'));
        static::assertStringContainsString(PlatformRequest::HEADER_MCP_PROTOCOL_VERSION, (string) $headers->get('Access-Control-Expose-Headers'));
    }

    public function testProvidersContributeAdditionalHeaders(): void
    {
        $listener = new CorsListener([
            new StaticCorsHeaderProvider(['ct-subscription-plan'], []),
            new StaticCorsHeaderProvider([], ['ct-subscription-state']),
        ]);

        $headers = $this->dispatchResponse($listener);
        static::assertStringContainsString('ct-subscription-plan', (string) $headers->get('Access-Control-Allow-Headers'));
        static::assertStringNotContainsString('ct-subscription-state', (string) $headers->get('Access-Control-Allow-Headers'));
        static::assertStringContainsString('ct-subscription-state', (string) $headers->get('Access-Control-Expose-Headers'));
    }

    public function testContributedHeadersAreDeduplicatedCaseInsensitively(): void
    {
        $listener = new CorsListener([
            new StaticCorsHeaderProvider(['Authorization', 'CT-Context-Token'], []),
            new StaticCorsHeaderProvider(['authorization'], []),
        ]);

        $allowed = explode(',', (string) $this->dispatchResponse($listener)->get('Access-Control-Allow-Headers'));
        static::assertCount(1, array_keys($allowed, 'Authorization', true));
        static::assertNotContains('CT-Context-Token', $allowed);
        static::assertContains(PlatformRequest::HEADER_CONTEXT_TOKEN, $allowed);
    }

    private function dispatchResponse(CorsListener $listener): ResponseHeaderBag
    {
        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/channel-api/subscription/plan', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $listener->onKernelResponse($event);

        return $event->getResponse()->headers;
    }
}

class StaticCorsHeaderProvider implements CorsHeaderProviderInterface
{
    public function __construct(private readonly array $allowed, private readonly array $exposed)
    {
    }

    public function getAllowedHeaders(): array
    {
        return $this->allowed;
    }

    public function getExposedHeaders(): array
    {
        return $this->exposed;
    }
}
