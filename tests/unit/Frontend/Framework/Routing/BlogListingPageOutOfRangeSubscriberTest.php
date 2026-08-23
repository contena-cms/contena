<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Content\Blog\BlogException;
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
        static::assertSame(['onKernelException', 10], $events[KernelEvents::EXCEPTION]);
    }

    public function testRedirectsWithStrippedPParameterOnFrontendRequest(): void
    {
        $event = $this->buildEvent('/articles/?p=99', true, BlogException::pageOutOfRange(99, 3));

        new BlogListingPageOutOfRangeSubscriber()->onKernelException($event);

        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
        static::assertSame('/articles/', $response->getTargetUrl());
    }

    public function testPreservesOtherQueryParameters(): void
    {
        $event = $this->buildEvent('/search?search=guide&p=42&order=name-asc&tag=release', true, BlogException::pageOutOfRange(42, 2));

        new BlogListingPageOutOfRangeSubscriber()->onKernelException($event);

        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        $target = $response->getTargetUrl();
        static::assertStringStartsWith('/search?', $target);
        static::assertStringNotContainsString('p=', $target);
        static::assertStringContainsString('search=guide', $target);
        static::assertStringContainsString('order=name-asc', $target);
        static::assertStringContainsString('tag=release', $target);
    }

    public function testFallsBackToCurrentRequestUriWhenOriginalAttributeMissing(): void
    {
        $request = new Request(['p' => 5]);
        $request->server->set('REQUEST_URI', '/some-path?p=5');
        $request->attributes->set(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST, true);
        $event = new ExceptionEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, BlogException::pageOutOfRange(5, 1));

        new BlogListingPageOutOfRangeSubscriber()->onKernelException($event);

        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/some-path', $response->getTargetUrl());
    }

    public static function provideNonRedirectCases(): \Generator
    {
        yield 'non-frontend request' => [false, BlogException::pageOutOfRange(99, 3)];
        yield 'unrelated exception' => [true, new \RuntimeException('something else')];
        yield 'different blog exception' => [true, BlogException::categoryNotFound('does-not-matter')];
    }

    #[DataProvider('provideNonRedirectCases')]
    public function testDoesNothingWhenNotApplicable(bool $isChannel, \Throwable $exception): void
    {
        $event = $this->buildEvent('/articles/?p=99', $isChannel, $exception);

        new BlogListingPageOutOfRangeSubscriber()->onKernelException($event);

        static::assertNull($event->getResponse());
    }

    public function testDoesNotClobberResponseAlreadySetByEarlierListener(): void
    {
        $event = $this->buildEvent('/articles/?p=99', true, BlogException::pageOutOfRange(99, 3));
        $existingResponse = new Response('custom body', Response::HTTP_GONE);
        $event->setResponse($existingResponse);

        new BlogListingPageOutOfRangeSubscriber()->onKernelException($event);

        static::assertSame($existingResponse, $event->getResponse());
    }

    private function buildEvent(string $originalUri, bool $isChannel, \Throwable $exception): ExceptionEvent
    {
        $request = new Request();
        if ($isChannel) {
            $request->attributes->set(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST, true);
        }
        $request->attributes->set(RequestTransformer::ORIGINAL_REQUEST_URI, $originalUri);

        return new ExceptionEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
