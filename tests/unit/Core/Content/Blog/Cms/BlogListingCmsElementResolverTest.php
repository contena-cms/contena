<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Listing\AbstractBlogListingRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRouteResponse;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingCollection;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingEntity;
use Contena\Core\Content\Blog\Cms\BlogListingCmsElementResolver;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BlogListingStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogListingCmsElementResolver::class)]
class BlogListingCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $route = static::createStub(AbstractBlogListingRoute::class);
        /** @var StaticEntityRepository<BlogSortingCollection> */
        $repository = new StaticEntityRepository([]);

        $resolver = new BlogListingCmsElementResolver($route, $repository);
        static::assertSame('blog-listing', $resolver->getType());
    }

    public function testGetCollectReturnsNull(): void
    {
        $route = static::createStub(AbstractBlogListingRoute::class);
        /** @var StaticEntityRepository<BlogSortingCollection> */
        $repository = new StaticEntityRepository([]);

        $slot = new CmsSlotEntity();
        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $resolver = new BlogListingCmsElementResolver($route, $repository);
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testEnrichHandlesDefaultSorting(): void
    {
        $config = new FieldConfigCollection([
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);
        $slot->setTranslated([
            'config' => [
                'useCustomSorting' => [
                    'value' => true,
                ],
                'defaultSorting' => [
                    'value' => 'sorting-id-1',
                ],
            ],
        ]);
        $request = new Request();
        $context = new ResolverContext(Generator::generateChannelContext(), $request);
        $data = new ElementDataCollection();

        $expectedResult = static::createStub(BlogListingResult::class);
        $response = new BlogListingRouteResponse($expectedResult);

        $route = $this->createMock(AbstractBlogListingRoute::class);
        $route->expects($this->once())->method('load')->willReturn($response);

        $sorting = new BlogSortingCollection([
            (new BlogSortingEntity())->assign([
                'id' => 'sorting-1',
                'key' => 'expected-sorting',
            ]),
        ]);

        /** @var StaticEntityRepository<BlogSortingCollection> */
        $repository = new StaticEntityRepository([$sorting]);

        $resolver = new BlogListingCmsElementResolver($route, $repository);
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(BlogListingStruct::class, $data);
        static::assertInstanceOf(BlogListingResult::class, $data->getListing());

        $this->assertRequestPayload($request);
    }

    public function testEnrichHandlesAvailableSorting(): void
    {
        $config = new FieldConfigCollection([
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);
        $slot->setTranslated([
            'config' => [
                'useCustomSorting' => [
                    'value' => true,
                ],
            ],
        ]);
        $request = new Request([
            'availableSortings' => [
                'sorting-id' => 'sorting-id-1',
            ],
        ]);
        $context = new ResolverContext(Generator::generateChannelContext(), $request);
        $data = new ElementDataCollection();

        $expectedResult = static::createStub(BlogListingResult::class);
        $response = new BlogListingRouteResponse($expectedResult);

        $route = $this->createMock(AbstractBlogListingRoute::class);
        $route->expects($this->once())->method('load')->willReturn($response);

        $sorting = new BlogSortingCollection([
            (new BlogSortingEntity())->assign([
                'id' => 'sorting-1',
                'key' => 'expected-sorting',
            ]),
        ]);

        /** @var StaticEntityRepository<BlogSortingCollection> */
        $repository = new StaticEntityRepository([$sorting]);

        $resolver = new BlogListingCmsElementResolver($route, $repository);
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(BlogListingStruct::class, $data);
        static::assertInstanceOf(BlogListingResult::class, $data->getListing());

        $this->assertRequestPayload($request);
    }

    private function assertRequestPayload(Request $request): void
    {
        static::assertNull($request->request->get('property-whitelist'));
        static::assertTrue($request->request->get('manufacturer-filter'));
        static::assertTrue($request->request->get('rating-filter'));
        static::assertTrue($request->request->get('shipping-free-filter'));
        static::assertTrue($request->request->get('price-filter'));
        static::assertTrue($request->request->get('property-filter'));
        static::assertSame('expected-sorting', $request->request->get('order'));
    }
}
