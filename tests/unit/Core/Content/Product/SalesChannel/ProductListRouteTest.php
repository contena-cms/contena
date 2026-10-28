<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\Extension\ProductListRouteExtension;
use Contena\Core\Content\Product\SalesChannel\ProductListResponse;
use Contena\Core\Content\Product\SalesChannel\ProductListRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductListRoute::class)]
class ProductListRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();
        $response = static::createStub(ProductListResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('product-list-route.load.pre', static function (ProductListRouteExtension $extension) use ($criteria, $context, $response): void {
            static::assertSame(['criteria' => $criteria, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ProductListRoute(
            static::createStub(SalesChannelRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($criteria, $context));
    }
}
