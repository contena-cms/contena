<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\Cms\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Contena\Core\Content\Product\Cms\ProductListingCmsElementResolver;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Contena\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductListingCmsElementResolver::class)]
class ProductListingTypeDataResolverTest extends TestCase
{
    private ProductListingCmsElementResolver $listingResolver;

    protected function setUp(): void
    {
        $mock = static::createStub(ProductListingRoute::class);
        $mock->method('load')->willReturn(
            new ProductListingRouteResponse(
                new ProductListingResult('product', 0, new ProductCollection(), null, new Criteria(), Context::createDefaultContext())
            )
        );

        /** @var StaticEntityRepository<ProductSortingCollection> */
        $sortingRepository = new StaticEntityRepository([new ProductSortingCollection()]);

        $this->listingResolver = new ProductListingCmsElementResolver($mock, $sortingRepository);
    }

    public function testGetType(): void
    {
        static::assertSame('product-listing', $this->listingResolver->getType());
    }

    public function testCollect(): void
    {
        $resolverContext = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');

        $collection = $this->listingResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithoutListingContext(): void
    {
        $resolverContext = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');

        $this->listingResolver->enrich($slot, $resolverContext, $result);

        $productListingStruct = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $productListingStruct);
        static::assertInstanceOf(EntitySearchResult::class, $productListingStruct->getListing());
    }
}
