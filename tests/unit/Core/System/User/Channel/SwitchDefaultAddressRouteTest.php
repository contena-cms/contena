<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\User\Channel\SwitchDefaultAddressRoute;
use Contena\Core\System\User\Extension\SwitchDefaultAddressRouteExtension;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(SwitchDefaultAddressRoute::class)]
class SwitchDefaultAddressRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $addressId = Uuid::randomHex();
        $type = SwitchDefaultAddressRoute::TYPE_SHIPPING;
        $context = Generator::generateChannelContext();
        $user = new UserEntity();
        $response = new NoContentResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('switch-default-address-route.swap.pre', static function (SwitchDefaultAddressRouteExtension $extension) use ($addressId, $type, $context, $user, $response): void {
            static::assertSame(['addressId' => $addressId, 'type' => $type, 'context' => $context, 'user' => $user], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new SwitchDefaultAddressRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->swap($addressId, $type, $context, $user));
    }
}
