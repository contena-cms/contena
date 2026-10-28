<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\SalesChannel\Struct\CrossSellingStruct;
use Contena\Core\Content\Product\Cms\CrossSellingCmsElementResolver;
use Contena\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Contena\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElement;
use Contena\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Contena\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRouteResponse;
use Contena\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Contena\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CrossSellingCmsElementResolver::class)]
class CrossSellingCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $route = static::createStub(AbstractProductCrossSellingRoute::class);
        $resolver = new CrossSellingCmsElementResolver($route);
        static::assertSame('cross-selling', $resolver->getType());
    }

    public function testEnrichStaticSlotWithCrossSelling(): void
    {
        $productId = 'product-1';

        $response = new ProductCrossSellingRouteResponse(new CrossSellingElementCollection([
        ]));

        $route = static::createStub(AbstractProductCrossSellingRoute::class);
        $route->method('load')->willReturn($response);

        $resolver = new CrossSellingCmsElementResolver($route);
        $config = new FieldConfigCollection([
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new SalesChannelProductCollection([$product]));

        $data = new ElementDataCollection();
        $data->add('product_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(CrossSellingStruct::class, $data);

        $collection = $data->getCrossSellings();
        static::assertInstanceOf(CrossSellingElementCollection::class, $collection);

        $crossSelling = $collection->first();
        static::assertInstanceOf(CrossSellingElement::class, $crossSelling);
        static::assertSame(1, $crossSelling->getTotal());
    }

    public function testEnrichSetsEmptyCrossSellingWithoutConfig(): void
    {
        $route = static::createStub(AbstractProductCrossSellingRoute::class);
        $resolver = new CrossSellingCmsElementResolver($route);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());
        $data = new ElementDataCollection();

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(CrossSellingStruct::class, $data);
        static::assertNull($data->getCrossSellings());
    }
}
