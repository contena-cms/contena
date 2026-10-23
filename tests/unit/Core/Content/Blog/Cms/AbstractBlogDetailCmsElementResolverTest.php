<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Cms\AbstractBlogDetailCmsElementResolver;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AbstractBlogDetailCmsElementResolver::class)]
class AbstractBlogDetailCmsElementResolverTest extends TestCase
{
    public function testCollectReturnsNullIfEntityResolverContextProvided(): void
    {
        $config = new FieldConfigCollection([
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $blog = new ChannelBlogEntity();
        $blog->setId('blog-id-1');

        $context = new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            new ChannelBlogDefinition(),
            $blog,
        );

        $resolver = new TestBlogDetailCmsElementResolver();
        $collection = $resolver->collect($slot, $context);
        static::assertNull($collection);
    }

    public function testCollectReturnsNullIfNoConfigProvided(): void
    {
        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $resolver = new TestBlogDetailCmsElementResolver();
        $collection = $resolver->collect($slot, $context);
        static::assertNull($collection);
    }

    public function testCollectBlogCriteria(): void
    {
        $config = new FieldConfigCollection([
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $resolver = new TestBlogDetailCmsElementResolver();
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $elements = $collection->all();
        static::assertCount(1, $elements);
        static::assertArrayHasKey(ChannelBlogDefinition::class, $elements);

        $definition = $elements[ChannelBlogDefinition::class];
        static::assertArrayHasKey('blog_slot-1', $definition);

        $criteria = $definition['blog_slot-1'];
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame('cms::blog-detail-static', $criteria->getTitle());
        static::assertArrayHasKey('properties', $criteria->getAssociations());
        static::assertArrayHasKey('group', $criteria->getAssociations()['properties']->getAssociations());
    }

    public function testGetSlotBlogReturnsNullIfNoSearchResultProvided(): void
    {
        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $data = new ElementDataCollection();
        $resolver = new TestBlogDetailCmsElementResolver();

        static::assertNull($resolver->runGetSlotBlog($slot, $data, 'blog-1'));
    }

    public function testGetSlotBlog(): void
    {
        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $entity = new ChannelBlogEntity();
        $entity->setUniqueIdentifier('blog-1');

        $collection = new ChannelBlogCollection([$entity]);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($collection);

        $data = new ElementDataCollection();
        $data->add('blog_slot-1', $result);

        $resolver = new TestBlogDetailCmsElementResolver();
        $blog = $resolver->runGetSlotBlog($slot, $data, 'blog-1');

        static::assertInstanceOf(ChannelBlogEntity::class, $blog);
    }
}
