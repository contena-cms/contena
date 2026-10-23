<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Cms\Type;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Blog\Cms\AbstractBlogDetailCmsElementResolver;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class BlogDetailCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, 'blog123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('dummy-type');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = (new BlogDetailCmsElementResolver())->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, $criteriaCollection->all());
        $criteria = $criteriaCollection->all()[ChannelBlogDefinition::class]['blog_id'];

        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertCount(1, $criteria->getFilters());
        static::assertInstanceOf(OrFilter::class, $orFilter = $criteria->getFilters()[0]);
        static::assertCount(2, $queries = $orFilter->getQueries());
        static::assertInstanceOf(EqualsFilter::class, $firstQuery = $queries[0]);
        static::assertSame('blog.parentId', $firstQuery->getField());
        static::assertSame('blog123', $firstQuery->getValue());
        static::assertInstanceOf(EqualsFilter::class, $secondQuery = $queries[1]);
        static::assertSame('id', $secondQuery->getField());
        static::assertSame('blog123', $secondQuery->getValue());
    }

    public function testCollectWithEmptyStaticConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('dummy-type');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = (new BlogDetailCmsElementResolver())->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithMappedConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_MAPPED, 'blog.name'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('dummy-type');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = (new BlogDetailCmsElementResolver())->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithEntityResolver(): void
    {
        $blog = new ChannelBlogEntity();
        $blog->setId('blog-01');
        $entityResolverContext = new EntityResolverContext(
            static::createStub(ChannelContext::class),
            new Request(),
            static::createStub(ChannelBlogDefinition::class),
            $blog
        );

        $fieldConfig = new FieldConfigCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('dummy-type');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = (new BlogDetailCmsElementResolver())->collect($slot, $entityResolverContext);

        static::assertNull($criteriaCollection);
        static::assertInstanceOf(FieldConfig::class, $blogConfig = $fieldConfig->get('blog'));
        static::assertSame(FieldConfig::SOURCE_MAPPED, $blogConfig->getSource());
        static::assertSame($blog->getId(), $blogConfig->getValue());
    }
}

/**
 * @internal
 */
class BlogDetailCmsElementResolver extends AbstractBlogDetailCmsElementResolver
{
    public function getType(): string
    {
        return 'dummy-type';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        // TODO: Implement enrich() method.
    }
}
