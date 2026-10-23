<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadEntity;
use Contena\Core\Content\Media\File\DownloadResponseGenerator;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Channel\DownloadRoute;
use Contena\Core\System\User\Extension\DownloadRouteExtension;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(DownloadRoute::class)]
class DownloadRouteTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<OrderLineItemDownloadCollection>
     */
    private Stub&EntityRepository $downloadRepository;

    private Stub&DownloadResponseGenerator $downloadResponseGenerator;

    private Stub&ChannelContext $channelContext;

    private DownloadRoute $downloadRoute;

    protected function setUp(): void
    {
        $this->downloadRepository = static::createStub(EntityRepository::class);
        $this->downloadResponseGenerator = static::createStub(DownloadResponseGenerator::class);
        $this->channelContext = static::createStub(ChannelContext::class);

        $this->downloadRoute = new DownloadRoute(
            $this->downloadRepository,
            $this->downloadResponseGenerator,
            new ExtensionDispatcher(new EventDispatcher())
        );
    }

    public function testGetDecoratedThrowsException(): void
    {
        static::expectException(DecorationPatternException::class);

        $this->downloadRoute->getDecorated();
    }

    public function testUserNotLoggedInException(): void
    {
        $this->expectExceptionObject(UserException::userNotLoggedIn());

        $this->downloadRoute->load(new Request(), $this->channelContext);
    }

    public function testMissingRequestParameterException(): void
    {
        $this->channelContext->method('getUser')->willReturn(new UserEntity());

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(RoutingException::class);
        } else {
            $this->expectException(UserException::class);
        }
        $this->downloadRoute->load(new Request(), $this->channelContext);
    }

    public function testDownloadNotExistingThrowsException(): void
    {
        $user = new UserEntity();
        $user->setId('foobar');
        $this->channelContext->method('getUser')->willReturn($user);

        $searchResult = static::createStub(EntitySearchResult::class);
        $this->downloadRepository->method('search')->willReturn($searchResult);

        $request = new Request();
        $request->attributes->set('downloadId', 'foo');
        $request->attributes->set('orderId', 'bar');

        $this->expectExceptionObject(UserException::downloadFileNotFound('foo'));
        $this->downloadRoute->load($request, $this->channelContext);
    }

    public function testReturnsResponse(): void
    {
        $user = new UserEntity();
        $user->setId('foobar');
        $this->channelContext->method('getUser')->willReturn($user);

        $searchResult = static::createStub(EntitySearchResult::class);
        $download = new OrderLineItemDownloadEntity();
        $download->setId('foo');
        $download->setMedia(new MediaEntity());
        $searchResult->method('getEntities')->willReturn(new OrderLineItemDownloadCollection([$download]));
        $this->downloadRepository->method('search')->willReturn($searchResult);

        $this->downloadResponseGenerator->method('getResponse')->willReturn(new Response());

        $request = new Request();
        $request->attributes->set('downloadId', 'foo');
        $request->attributes->set('orderId', 'bar');

        $response = $this->downloadRoute->load($request, $this->channelContext);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $response = new Response();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('download-route.load.pre', function (DownloadRouteExtension $extension) use ($request, $response): void {
            static::assertSame(['request' => $request, 'context' => $this->channelContext], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new DownloadRoute(
            $this->downloadRepository,
            $this->downloadResponseGenerator,
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $this->channelContext));
    }
}
