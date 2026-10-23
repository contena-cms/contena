<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\CrossSelling\AbstractBlogCrossSellingRoute;
use Contena\Core\Content\Blog\Channel\CrossSelling\BlogCrossSellingRouteResponse;
use Contena\Core\Content\Blog\Channel\CrossSelling\CrossSellingElement;
use Contena\Core\Content\Blog\Channel\CrossSelling\CrossSellingElementCollection;
use Contena\Core\Content\Blog\Cms\CrossSellingCmsElementResolver;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\CrossSellingStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CrossSellingCmsElementResolver::class)]
class CrossSellingCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $route = static::createStub(AbstractBlogCrossSellingRoute::class);
        $resolver = new CrossSellingCmsElementResolver($route);
        static::assertSame('cross-selling', $resolver->getType());
    }

    public function testEnrichStaticSlotWithCrossSelling(): void
    {
        $blogId = 'blog-1';

        $response = new BlogCrossSellingRouteResponse(new CrossSellingElementCollection([
            (new CrossSellingElement())->assign(['total' => 1]),
        ]));

        $route = static::createStub(AbstractBlogCrossSellingRoute::class);
        $route->method('load')->willReturn($response);

        $resolver = new CrossSellingCmsElementResolver($route);
        $config = new FieldConfigCollection([
            new FieldConfig('blog', FieldConfig::SOURCE_STATIC, $blogId),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ChannelBlogCollection([$blog]));

        $data = new ElementDataCollection();
        $data->add('blog_slot-1', $result);

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
        $route = static::createStub(AbstractBlogCrossSellingRoute::class);
        $resolver = new CrossSellingCmsElementResolver($route);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());
        $data = new ElementDataCollection();

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(CrossSellingStruct::class, $data);
        static::assertNull($data->getCrossSellings());
    }
}
