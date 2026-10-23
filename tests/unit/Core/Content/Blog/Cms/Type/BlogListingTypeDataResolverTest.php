<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRouteResponse;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingCollection;
use Contena\Core\Content\Blog\Cms\BlogListingCmsElementResolver;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BlogListingStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogListingCmsElementResolver::class)]
class BlogListingTypeDataResolverTest extends TestCase
{
    private BlogListingCmsElementResolver $listingResolver;

    protected function setUp(): void
    {
        $mock = static::createStub(BlogListingRoute::class);
        $mock->method('load')->willReturn(
            new BlogListingRouteResponse(
                new BlogListingResult('blog', 0, new BlogCollection(), null, new Criteria(), Context::createDefaultContext())
            )
        );

        /** @var StaticEntityRepository<BlogSortingCollection> */
        $sortingRepository = new StaticEntityRepository([new BlogSortingCollection()]);

        $this->listingResolver = new BlogListingCmsElementResolver($mock, $sortingRepository);
    }

    public function testGetType(): void
    {
        static::assertSame('blog-listing', $this->listingResolver->getType());
    }

    public function testCollect(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-listing');

        $collection = $this->listingResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithoutListingContext(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-listing');

        $this->listingResolver->enrich($slot, $resolverContext, $result);

        $blogListingStruct = $slot->getData();
        static::assertInstanceOf(BlogListingStruct::class, $blogListingStruct);
        static::assertInstanceOf(EntitySearchResult::class, $blogListingStruct->getListing());
    }
}
