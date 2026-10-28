<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\Extension\RemoveWishlistProductRouteExtension;
use Contena\Core\Checkout\Customer\SalesChannel\RemoveWishlistProductRoute;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\SuccessResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RemoveWishlistProductRoute::class)]
class RemoveWishlistProductRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $productId = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('remove-wishlist-product-route.delete.pre', static function (RemoveWishlistProductRouteExtension $extension) use ($productId, $context, $customer, $response): void {
            static::assertSame(['productId' => $productId, 'context' => $context, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new RemoveWishlistProductRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(SystemConfigService::class),
            static::createStub(EventDispatcherInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->delete($productId, $context, $customer));
    }
}
