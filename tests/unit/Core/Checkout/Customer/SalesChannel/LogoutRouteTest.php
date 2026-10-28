<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\SalesChannel\CartService;
use Contena\Core\Checkout\Customer\Extension\LogoutRouteExtension;
use Contena\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Contena\Core\System\SalesChannel\ContextTokenResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LogoutRoute::class)]
class LogoutRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $context = Generator::generateSalesChannelContext();
        $data = new RequestDataBag();
        $response = new ContextTokenResponse('token');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('logout-route.logout.pre', static function (LogoutRouteExtension $extension) use ($context, $data, $response): void {
            static::assertSame(['context' => $context, 'data' => $data], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new LogoutRoute(
            static::createStub(SalesChannelContextPersister::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(CartService::class),
            static::createStub(SalesChannelContextServiceInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->logout($context, $data));
    }
}
