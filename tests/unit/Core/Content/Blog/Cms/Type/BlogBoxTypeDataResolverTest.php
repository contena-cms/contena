<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Cms\BlogBoxCmsElementResolver;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BlogBoxStruct;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogBoxCmsElementResolver::class)]
class BlogBoxTypeDataResolverTest extends TestCase
{
    private BlogBoxCmsElementResolver $blogBoxResolver;

    private Stub&SystemConfigService $systemConfig;

    protected function setUp(): void
    {
        $this->systemConfig = static::createStub(SystemConfigService::class);
        $this->blogBoxResolver = new BlogBoxCmsElementResolver($this->systemConfig);
    }

    public function testType(): void
    {
        static::assertSame('blog-box', $this->blogBoxResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-box');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->blogBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, 'blog123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->blogBoxResolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, $criteriaCollection->all());
        static::assertSame(['blog123'], $criteriaCollection->all()[BlogDefinition::class]['blog_id']->getIds());
    }

    public function testCollectWithMappedConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_MAPPED, 'entity.relatedBlog'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->blogBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-box');
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->blogBoxResolver->enrich($slot, $resolverContext, $result);

        $blogBoxStruct = $slot->getData();
        static::assertInstanceOf(BlogBoxStruct::class, $blogBoxStruct);
        static::assertNull($blogBoxStruct->getBlogId());
        static::assertNull($blogBoxStruct->getBlog());
    }

    #[DataProvider('enrichWithStaticConfigProvider')]
    public function testEnrichWithStaticConfig(bool $closeout, bool $hidden, int $availableStock): void
    {
        if ($hidden) {
            $this->systemConfig->method('getBool')->willReturn(true);
        }

        $channelId = 'f3489c46df62422abdea4aa1bb03511c';

        $blog = new ChannelBlogEntity();
        $blog->setId('blog123');
        $blog->setStock($availableStock);
        $blog->setIsCloseout($closeout);
        $blog->setAvailableStock($availableStock);
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $channel = new ChannelEntity();
        $channel->setId($channelId);

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn($channelId);
        $channelContext->method('getChannel')->willReturn($channel);

        $resolverContext = new ResolverContext($channelContext, new Request());
        $result = new ElementDataCollection();
        $result->add('blog_id', new EntitySearchResult(
            'blog',
            1,
            new BlogCollection([clone $blog]),
            null,
            new Criteria(),
            $resolverContext->getChannelContext()->getContext()
        ));

        $slot = $this->enrichBlogWithCmsConfig($blog);

        $this->blogBoxResolver->enrich($slot, $resolverContext, $result);
        $blogBoxStruct = $slot->getData();
        static::assertInstanceOf(BlogBoxStruct::class, $blogBoxStruct);

        /*
         * conditional assertions depending on if a blog should be returned or not
         */
        if ($closeout && $hidden && $availableStock === 0) {
            static::assertNull($blogBoxStruct->getBlogId());
        } else {
            static::assertSame($blogBoxStruct->getBlogId(), $blog->getId());
            static::assertNotSame($blog, $blogBoxStruct->getBlog());

            $serializedBlog = json_encode($blog);
            static::assertNotSame(\JSON_ERROR_RECURSION, json_last_error());
            static::assertNotFalse($serializedBlog);
        }
    }

    /**
     * @return iterable<string, array{closeout: bool, hidden: bool, availableStock: int}>
     */
    public static function enrichWithStaticConfigProvider(): iterable
    {
        yield 'visible blog with stock is enriched' => ['closeout' => false, 'hidden' => false, 'availableStock' => 1];
        yield 'hidden blog with stock is enriched when closeout is disabled' => ['closeout' => false, 'hidden' => true,  'availableStock' => 1];
        yield 'closeout blog with stock is enriched when it is visible' => ['closeout' => true, 'hidden' => false, 'availableStock' => 1];
        yield 'hidden closeout blog with stock is enriched' => ['closeout' => true, 'hidden' => true,  'availableStock' => 1];
        yield 'hidden closeout blog without stock is not enriched' => ['closeout' => true, 'hidden' => true,  'availableStock' => 0];
    }

    public function testEnrichWithStaticConfigButNoResult(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();
        $result->add('blog_id', new EntitySearchResult(
            'blog',
            0,
            new BlogCollection(),
            null,
            new Criteria(),
            $resolverContext->getChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, 'blog123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-box');
        $slot->setFieldConfig($fieldConfig);

        $this->blogBoxResolver->enrich($slot, $resolverContext, $result);

        $blogBoxStruct = $slot->getData();
        static::assertInstanceOf(BlogBoxStruct::class, $blogBoxStruct);
        static::assertNull($blogBoxStruct->getBlogId());
        static::assertNull($blogBoxStruct->getBlog());
    }

    public function testEnrichWithMappedConfig(): void
    {
        $parent = new ChannelBlogEntity();
        $parent->setId('blog_parent');

        $blog = new ChannelBlogEntity();
        $blog->setId('blog123');
        $blog->setParent($parent);

        $resolverContext = new EntityResolverContext(static::createStub(ChannelContext::class), new Request(), static::createStub(BlogDefinition::class), $blog);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_MAPPED, 'blog.parent'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-box');
        $slot->setFieldConfig($fieldConfig);

        $this->blogBoxResolver->enrich($slot, $resolverContext, $result);

        $blogBoxStruct = $slot->getData();
        static::assertInstanceOf(BlogBoxStruct::class, $blogBoxStruct);
        static::assertSame($parent->getId(), $blogBoxStruct->getBlogId());
    }

    public function testEnrichWithMappedConfigButInvalidProperty(): void
    {
        $blog = new ChannelBlogEntity();
        $blog->setId('blog123');

        $resolverContext = new EntityResolverContext(static::createStub(ChannelContext::class), new Request(), static::createStub(BlogDefinition::class), $blog);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_MAPPED, 'blog.foo'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-box');
        $slot->setFieldConfig($fieldConfig);

        $this->expectExceptionObject(new PropertyNotFoundException('foo', ChannelBlogEntity::class));

        $this->blogBoxResolver->enrich($slot, $resolverContext, $result);
    }

    public function testCollectWithEmptyBlogId(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->blogBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    protected function enrichBlogWithCmsConfig(ChannelBlogEntity $blog): CmsSlotEntity
    {
        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, $blog->getId()));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setSlot('blog-box');
        $slot->setType('blog-box');
        $slot->setFieldConfig($fieldConfig);
        $slot->getFieldConfig();

        $cmsBlock = new CmsBlockEntity();
        $cmsBlock->setUniqueIdentifier('block1');
        $cmsBlock->setSlots(new CmsSlotCollection([$slot]));

        $cmsSection = new CmsSectionEntity();
        $cmsSection->setUniqueIdentifier('section1');
        $cmsSection->setBlocks(new CmsBlockCollection([$cmsBlock]));

        $cmsPage = new CmsPageEntity();
        $cmsPage->setSections(new CmsSectionCollection([$cmsSection]));

        $blog->setCmsPage($cmsPage);

        return $slot;
    }
}
