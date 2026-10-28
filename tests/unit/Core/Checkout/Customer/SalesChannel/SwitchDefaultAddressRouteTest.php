<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\Extension\SwitchDefaultAddressRouteExtension;
use Contena\Core\Checkout\Customer\SalesChannel\SwitchDefaultAddressRoute;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\NoContentResponse;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SwitchDefaultAddressRoute::class)]
class SwitchDefaultAddressRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $addressId = Uuid::randomHex();
        $type = SwitchDefaultAddressRoute::TYPE_SHIPPING;
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $response = new NoContentResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('switch-default-address-route.swap.pre', static function (SwitchDefaultAddressRouteExtension $extension) use ($addressId, $type, $context, $customer, $response): void {
            static::assertSame(['addressId' => $addressId, 'type' => $type, 'context' => $context, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new SwitchDefaultAddressRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->swap($addressId, $type, $context, $customer));
    }
}
