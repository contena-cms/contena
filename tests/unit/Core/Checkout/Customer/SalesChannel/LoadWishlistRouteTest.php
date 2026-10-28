<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\Extension\LoadWishlistRouteExtension;
use Contena\Core\Checkout\Customer\SalesChannel\LoadWishlistRoute;
use Contena\Core\Checkout\Customer\SalesChannel\LoadWishlistRouteResponse;
use Contena\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoadWishlistRoute::class)]
class LoadWishlistRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $customer = new CustomerEntity();
        $response = static::createStub(LoadWishlistRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('load-wishlist-route.load.pre', static function (LoadWishlistRouteExtension $extension) use ($request, $context, $criteria, $customer, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new LoadWishlistRoute(
            static::createStub(EntityRepository::class),
            static::createStub(SalesChannelRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractProductCloseoutFilterFactory::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria, $customer));
    }
}
