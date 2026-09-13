<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\EventListener;

use Contena\Core\Framework\Api\Cors\CorsHeaderProviderInterface;
use Contena\Core\Framework\Api\Cors\CorsHeaders;
use Contena\Core\Framework\Api\EventListener\CorsListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
        $listener = new CorsListener([]);
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
        $listener = new CorsListener([]);
        $event = new RequestEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/api/_action/test', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onKernelRequest($event);

        static::assertNull($event->getResponse());
    }

    public function testSubRequestIsIgnored(): void
    {
        $listener = new CorsListener([]);
        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/api/_action/test', 'POST'),
            HttpKernelInterface::SUB_REQUEST,
            new Response(),
        );

        $listener->onKernelResponse($event);

        static::assertFalse($event->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    public function testOriginAndMethodsDoNotDependOnTheProviders(): void
    {
        $headers = $this->dispatchResponse(new CorsListener([]));

        static::assertSame('*', $headers->get('Access-Control-Allow-Origin'));
        static::assertSame('GET,POST,PUT,PATCH,DELETE', $headers->get('Access-Control-Allow-Methods'));
    }

    public function testWithoutProvidersBothHeaderListsAreEmpty(): void
    {
        $headers = $this->dispatchResponse(new CorsListener([]));

        static::assertSame('', $headers->get('Access-Control-Allow-Headers'));
        static::assertSame('', $headers->get('Access-Control-Expose-Headers'));
    }

    public function testProvidersContributeToBothListsSeparately(): void
    {
        $listener = new CorsListener([
            new CallbackCorsHeaderProvider(static function (CorsHeaders $headers): void {
                $headers->addAllowed('ct-plan', 'ct-interval');
            }),
            new CallbackCorsHeaderProvider(static function (CorsHeaders $headers): void {
                $headers->addAllowed('ct-source');
                $headers->addExposed('ct-state');
            }),
        ]);

        $headers = $this->dispatchResponse($listener);

        static::assertSame('ct-plan,ct-interval,ct-source', $headers->get('Access-Control-Allow-Headers'));
        static::assertSame('ct-state', $headers->get('Access-Control-Expose-Headers'));
    }

    public function testAProviderCanRemoveWhatAnEarlierProviderContributed(): void
    {
        $listener = new CorsListener([
            new CallbackCorsHeaderProvider(static function (CorsHeaders $headers): void {
                $headers->addAllowed('ct-plan', 'ct-interval');
                $headers->addExposed('ct-state');
            }),
            new CallbackCorsHeaderProvider(static function (CorsHeaders $headers): void {
                $headers->removeAllowed('CT-PLAN');
                $headers->removeExposed('ct-state');
            }),
        ]);

        $headers = $this->dispatchResponse($listener);

        static::assertSame('ct-interval', $headers->get('Access-Control-Allow-Headers'));
        static::assertSame('', $headers->get('Access-Control-Expose-Headers'));
    }

    public function testContributedHeadersAreDeduplicated(): void
    {
        $listener = new CorsListener([
            new CallbackCorsHeaderProvider(static function (CorsHeaders $headers): void {
                $headers->addAllowed('ct-plan');
            }),
            new CallbackCorsHeaderProvider(static function (CorsHeaders $headers): void {
                $headers->addAllowed('CT-Plan', 'ct-interval');
            }),
        ]);

        $headers = $this->dispatchResponse($listener);

        static::assertSame('ct-plan,ct-interval', $headers->get('Access-Control-Allow-Headers'));
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

class CallbackCorsHeaderProvider implements CorsHeaderProviderInterface
{
    /**
     * @param \Closure(CorsHeaders): void $contribute
     */
    public function __construct(private readonly \Closure $contribute)
    {
    }

    public function provide(CorsHeaders $headers): void
    {
        ($this->contribute)($headers);
    }
}
