<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\System\User\Extension\LogoutRouteExtension;
use Contena\Core\System\User\Channel\LogoutRoute;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(LogoutRoute::class)]
class LogoutRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $context = Generator::generateChannelContext();
        $data = new RequestDataBag();
        $response = new ContextTokenResponse('token');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('logout-route.logout.pre', static function (LogoutRouteExtension $extension) use ($context, $data, $response): void {
            static::assertSame(['context' => $context, 'data' => $data], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new LogoutRoute(
            static::createStub(ChannelContextPersister::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(CartService::class),
            static::createStub(ChannelContextServiceInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->logout($context, $data));
    }
}
