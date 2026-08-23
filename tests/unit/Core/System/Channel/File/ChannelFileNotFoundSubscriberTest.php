<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Kernel;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextRequestRestorer;
use Contena\Core\System\Channel\File\ChannelFileNotFoundSubscriber;
use Contena\Core\System\Channel\File\ChannelFileRequestPathResolver;
use Contena\Core\System\Channel\File\Loader\ChannelFileLoader;
use Contena\Core\System\Channel\File\Rendering\ChannelFileRenderResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(ChannelFileNotFoundSubscriber::class)]
#[CoversClass(ChannelFileRequestPathResolver::class)]
class ChannelFileNotFoundSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                KernelEvents::EXCEPTION => ['onNotFound', -90],
            ],
            ChannelFileNotFoundSubscriber::getSubscribedEvents(),
        );
    }

    public function testItServesChannelFileForUnresolvedNotFoundWithExistingChannelContext(): void
    {
        $context = static::createStub(ChannelContext::class);
        $request = Request::create('/llms.txt');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['channel-api']);

        $loader = $this->createMock(ChannelFileLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->with('files/agentic/llms.txt.twig', $context)
            ->willReturn(new ChannelFileRenderResult('llms.txt', 'Merchant llms', 'text/plain; charset=utf-8'));

        $event = $this->createExceptionEvent($request);

        $contextRestorer = $this->createMock(ChannelContextRequestRestorer::class);
        $contextRestorer
            ->expects($this->once())
            ->method('restore')
            ->with($request)
            ->willReturn($context);

        $this->createSubscriber($loader, $contextRestorer)->onNotFound($event);

        $response = $event->getResponse();
        static::assertInstanceOf(Response::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('text/plain; charset=utf-8', $response->headers->get('content-type'));
        static::assertSame('Merchant llms', $response->getContent());
        static::assertTrue($event->isAllowingCustomResponseCode());
        static::assertTrue($request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE));
        static::assertSame(['channel-api'], $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE));
    }

    public function testItServesChannelFileFromWellKnownSubFolder(): void
    {
        $context = static::createStub(ChannelContext::class);
        $request = Request::create('/.well-known/ucp.json');

        $loader = $this->createMock(ChannelFileLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->with('files/agentic/.well-known/ucp.json.twig', $context)
            ->willReturn(new ChannelFileRenderResult('.well-known/ucp.json', '{"custom": true}', 'application/json; charset=utf-8'));

        $event = $this->createExceptionEvent($request);

        $contextRestorer = $this->createMock(ChannelContextRequestRestorer::class);
        $contextRestorer
            ->expects($this->once())
            ->method('restore')
            ->with($request)
            ->willReturn($context);

        $this->createSubscriber($loader, $contextRestorer)->onNotFound($event);

        $response = $event->getResponse();
        static::assertInstanceOf(Response::class, $response);
        static::assertSame('{"custom": true}', $response->getContent());
    }

    public function testItUsesContextResolverForCandidateFilePath(): void
    {
        $context = static::createStub(ChannelContext::class);
        $request = Request::create('/llms.txt');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, 'channel-id');

        $contextRestorer = $this->createMock(ChannelContextRequestRestorer::class);
        $contextRestorer
            ->expects($this->once())
            ->method('restore')
            ->with($request)
            ->willReturn($context);

        $loader = $this->createMock(ChannelFileLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->with('files/agentic/llms.txt.twig', $context)
            ->willReturn(new ChannelFileRenderResult('llms.txt', 'Merchant llms', 'text/plain; charset=utf-8'));

        $event = $this->createExceptionEvent($request);

        $this->createSubscriber($loader, $contextRestorer)->onNotFound($event);

        static::assertSame('Merchant llms', $event->getResponse()?->getContent());
    }

    public function testItReturnsEarlyWithoutChannelContext(): void
    {
        $loader = $this->createMock(ChannelFileLoader::class);
        $loader
            ->expects($this->never())
            ->method('load');

        $event = $this->createExceptionEvent(Request::create('/llms.txt'));

        $contextRestorer = $this->createMock(ChannelContextRequestRestorer::class);
        $contextRestorer
            ->expects($this->once())
            ->method('restore')
            ->willReturn(null);

        $this->createSubscriber($loader, $contextRestorer)->onNotFound($event);

        static::assertNull($event->getResponse());
    }

    public function testItReturnsEarlyForRoutedNotFound(): void
    {
        $request = Request::create('/llms.txt');
        $request->attributes->set('_route', 'frontend.example');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, static::createStub(ChannelContext::class));

        $loader = $this->createMock(ChannelFileLoader::class);
        $loader
            ->expects($this->never())
            ->method('load');

        $event = $this->createExceptionEvent($request);

        $this->createSubscriber($loader)->onNotFound($event);

        static::assertNull($event->getResponse());
    }

    public function testItReturnsEarlyForInvalidPublicPath(): void
    {
        $request = Request::create('/folder/file');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, static::createStub(ChannelContext::class));

        $loader = $this->createMock(ChannelFileLoader::class);
        $loader
            ->expects($this->never())
            ->method('load');

        $event = $this->createExceptionEvent($request);

        $this->createSubscriber($loader)->onNotFound($event);

        static::assertNull($event->getResponse());
    }

    public function testItReturnsEarlyForNonNotFoundExceptions(): void
    {
        $request = Request::create('/llms.txt');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, static::createStub(ChannelContext::class));

        $loader = $this->createMock(ChannelFileLoader::class);
        $loader
            ->expects($this->never())
            ->method('load');

        $event = $this->createExceptionEvent($request, new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR));

        $this->createSubscriber($loader)->onNotFound($event);

        static::assertNull($event->getResponse());
    }

    private function createExceptionEvent(Request $request, ?\Throwable $throwable = null): ExceptionEvent
    {
        return new ExceptionEvent(
            static::createStub(Kernel::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $throwable ?? new NotFoundHttpException(),
        );
    }

    private function createSubscriber(ChannelFileLoader $loader, ?ChannelContextRequestRestorer $contextRestorer = null): ChannelFileNotFoundSubscriber
    {
        if ($contextRestorer === null) {
            $contextRestorer = $this->createMock(ChannelContextRequestRestorer::class);
            $contextRestorer
                ->expects($this->never())
                ->method('restore');
        }

        return new ChannelFileNotFoundSubscriber(
            $loader,
            new ChannelFileRequestPathResolver(),
            $contextRestorer,
        );
    }
}
