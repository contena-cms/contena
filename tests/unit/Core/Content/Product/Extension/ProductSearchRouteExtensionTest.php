<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\Extension\ProductSearchRouteExtension;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Contena\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Contena\Tests\Examples\ProductSearchRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductSearchRouteExtension::class)]
class ProductSearchRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesSearch(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ProductSearchRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: ProductSearchRouteExtension::NAME,
            extension: new ProductSearchRouteExtension(new Request(), $context, $criteria),
            function: static function () use (&$coreCalled): ProductSearchRouteResponse {
                $coreCalled = true;

                return new ProductSearchRouteResponse(new ProductListingResult(
                    ProductDefinition::ENTITY_NAME,
                    0,
                    new ProductCollection(),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                ));
            },
        );

        static::assertFalse($coreCalled, 'The core product search must be skipped when a subscriber resolves it.');
        static::assertSame($criteria, $result->getListingResult()->getCriteria());
    }
}
