<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\ChannelRequest;
use Contena\Frontend\Framework\Routing\BlogListingPageOutOfRangeSubscriber;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(BlogListingPageOutOfRangeSubscriber::class)]
class BlogListingPageOutOfRangeSubscriberTest extends TestCase
{
    public function testSubscribesToKernelExceptionWithExplicitPriority(): void
    {
        $events = BlogListingPageOutOfRangeSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        // Priority must be > -100 (NotFoundSubscriber) so we run before the 404 page is rendered.
        static::assertSame(['onKernelException', 10], $events[KernelEvents::EXCEPTION]);
    }

    public function testRedirectsWithStrippedPParameterOnFrontendRequest(): void
    {
        $event = $this->buildEvent(
            originalUri: '/Damen/?p=99',
            isChannel: true,
            exception: BlogException::pageOutOfRange(99, 3),
        );

        (new BlogListingPageOutOfRangeSubscriber())->onKernelException($event);

        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
        static::assertSame('/Damen/', $response->getTargetUrl());
    }

    public function testPreservesOtherQueryParameters(): void
    {
        $event = $this->buildEvent(
            originalUri: '/search?search=phone&p=42&order=price-asc&manufacturer-filter=apple',
            isChannel: true,
            exception: BlogException::pageOutOfRange(42, 2),
        );

        (new BlogListingPageOutOfRangeSubscriber())->onKernelException($event);

        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        $target = $response->getTargetUrl();
        static::assertStringStartsWith('/search?', $target);
        static::assertStringNotContainsString('p=42', $target);
        static::assertStringNotContainsString('p=', $target);
        static::assertStringContainsString('search=phone', $target);
        static::assertStringContainsString('order=price-asc', $target);
        static::assertStringContainsString('manufacturer-filter=apple', $target);
    }

    public function testFallsBackToCurrentRequestUriWhenOriginalAttributeMissing(): void
    {
        $request = new Request(['p' => 5]);
        $request->server->set('REQUEST_URI', '/some-path?p=5');
        $request->attributes->set(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST, true);

        $event = new ExceptionEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            BlogException::pageOutOfRange(5, 1),
        );

        (new BlogListingPageOutOfRangeSubscriber())->onKernelException($event);

        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/some-path', $response->getTargetUrl());
    }

    public static function provideNonRedirectCases(): \Generator
    {
        yield 'non-frontend request: do nothing (let 404 propagate)' => [
            'isChannel' => false,
            'exception' => BlogException::pageOutOfRange(99, 3),
        ];

        yield 'frontend request, unrelated exception: do nothing' => [
            'isChannel' => true,
            'exception' => new \RuntimeException('something else'),
        ];

        yield 'frontend request, BlogException with different code: do nothing' => [
            'isChannel' => true,
            'exception' => BlogException::categoryNotFound('does-not-matter'),
        ];
    }

    #[DataProvider('provideNonRedirectCases')]
    public function testDoesNothingWhenNotApplicable(bool $isChannel, \Throwable $exception): void
    {
        $event = $this->buildEvent(
            originalUri: '/Damen/?p=99',
            isChannel: $isChannel,
            exception: $exception,
        );

        (new BlogListingPageOutOfRangeSubscriber())->onKernelException($event);

        static::assertNull($event->getResponse());
    }

    public function testDoesNotClobberResponseAlreadySetByEarlierListener(): void
    {
        $event = $this->buildEvent(
            originalUri: '/Damen/?p=99',
            isChannel: true,
            exception: BlogException::pageOutOfRange(99, 3),
        );

        $existingResponse = new Response('custom soft-404 body', Response::HTTP_GONE);
        $event->setResponse($existingResponse);

        (new BlogListingPageOutOfRangeSubscriber())->onKernelException($event);

        static::assertSame($existingResponse, $event->getResponse());
    }

    private function buildEvent(string $originalUri, bool $isChannel, \Throwable $exception): ExceptionEvent
    {
        $request = new Request();
        if ($isChannel) {
            $request->attributes->set(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST, true);
        }
        $request->attributes->set(RequestTransformer::ORIGINAL_REQUEST_URI, $originalUri);

        return new ExceptionEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
