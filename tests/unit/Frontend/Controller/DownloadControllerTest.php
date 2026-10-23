<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Channel\DownloadRoute;
use Contena\Core\System\User\UserEntity;
use Contena\Frontend\Controller\DownloadController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[CoversClass(DownloadController::class)]
class DownloadControllerTest extends TestCase
{
    private Stub&DownloadRoute $downloadRouteMock;

    private DownloadController $controller;

    protected function setUp(): void
    {
        $this->downloadRouteMock = static::createStub(DownloadRoute::class);

        $this->controller = new DownloadController(
            $this->downloadRouteMock
        );
    }

    public function testLoggedOutResponseReturn(): void
    {
        $containerBuilder = new ContainerBuilder();
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with(
                'frontend.account.order.single.page',
                [
                    'deepLinkCode' => 'foo',
                ]
            )
            ->willReturn('bar');
        $containerBuilder->set('router', $router);
        $containerBuilder->set('event_dispatcher', static::createStub(EventDispatcherInterface::class));
        $this->controller->setContainer($containerBuilder);
        $this->downloadRouteMock->method('load')->willReturn(new Response());

        $request = new Request();
        $request->query->set('deepLinkCode', 'foo');

        $channelContext = static::createStub(ChannelContext::class);
        $response = $this->controller->downloadFile($request, $channelContext);

        static::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testLoggedInResponseReturn(): void
    {
        $this->downloadRouteMock->method('load')->willReturn(new Response());

        $channelContext = $this->createMock(ChannelContext::class);
        $channelContext->expects($this->once())->method('getUser')->willReturn(new UserEntity());
        $response = $this->controller->downloadFile(new Request(), $channelContext);

        static::assertNotInstanceOf(RedirectResponse::class, $response);
    }
}
