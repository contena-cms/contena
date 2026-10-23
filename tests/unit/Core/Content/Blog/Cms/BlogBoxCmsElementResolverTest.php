<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Cms\BlogBoxCmsElementResolver;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BlogBoxStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogBoxCmsElementResolver::class)]
class BlogBoxCmsElementResolverTest extends TestCase
{
    private BlogBoxCmsElementResolver $boxCmsElementResolver;

    private Stub&SystemConfigService $systemConfig;

    protected function setUp(): void
    {
        $this->systemConfig = static::createStub(SystemConfigService::class);

        $this->boxCmsElementResolver = new BlogBoxCmsElementResolver($this->systemConfig);
    }

    public function testGetType(): void
    {
        static::assertSame('blog-box', $this->boxCmsElementResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-box');
        $slot->setFieldConfig(new FieldConfigCollection());

        $collection = $this->boxCmsElementResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithEmptyStaticConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection([
        ]);

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-box');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->boxCmsElementResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $blogId = Uuid::randomHex();
        $fieldConfig = new FieldConfigCollection([
        ]);

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-box');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->boxCmsElementResolver->collect($slot, $resolverContext);

        static::assertNotNull($collection);
        static::assertCount(1, $collection->all());

        $criteria = $collection->all()[BlogDefinition::class]['blog_id'];
        static::assertSame([$blogId], $criteria->getIds());
        static::assertTrue($criteria->hasAssociation('manufacturer'));
        static::assertTrue($criteria->hasAssociation('options'));
        static::assertTrue($criteria->getAssociation('options')->hasAssociation('group'));
    }

    public function testCollectWithMappedConfigButWithoutEntityResolverContext(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection([
        ]);

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-box');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->boxCmsElementResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithNoBlogConfig(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $resolverContext = new ResolverContext($channelContext, new Request());
        $result = new ElementDataCollection();
        $fieldConfig = new FieldConfigCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('');
        $slot->setFieldConfig($fieldConfig);

        $this->boxCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var BlogBoxStruct|null $blogBoxStruct */
        $blogBoxStruct = $slot->getData();

        static::assertInstanceOf(BlogBoxStruct::class, $blogBoxStruct);

        $blog = $blogBoxStruct->getBlog();
        static::assertNull($blog);
    }

    public function testEnrichWithBlogConfigIsMapped(): void
    {
        $blogId = Uuid::randomHex();
        $channelContext = static::createStub(ChannelContext::class);
        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);
        $blog->setUniqueIdentifier('blog1');

        $resolverContext = new EntityResolverContext($channelContext, new Request(), static::createStub(ChannelBlogDefinition::class), $blog);
        $result = new ElementDataCollection();
        $fieldConfig = new FieldConfigCollection([
        ]);

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('');
        $slot->setFieldConfig($fieldConfig);

        $this->boxCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var BlogBoxStruct|null $blogBoxStruct */
        $blogBoxStruct = $slot->getData();

        static::assertInstanceOf(BlogBoxStruct::class, $blogBoxStruct);

        $blog = $blogBoxStruct->getBlog();
        static::assertNotNull($blog);
        static::assertSame($blogId, $blog->getId());
    }

    #[DataProvider('enrichDataProvider')]
    public function testEnrich(bool $closeout, bool $hidden, int $availableStock): void
    {
        if ($hidden) {
            $this->systemConfig->method('getBool')->willReturn(true);
        }

        $channelId = 'f3489c46df62422abdea4aa1bb03511c';
        $blogId = Uuid::randomHex();
        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);
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
            new BlogCollection([$blog]),
            null,
            new Criteria(),
            $resolverContext->getChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, $blogId));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('');
        $slot->setFieldConfig($fieldConfig);

        $this->boxCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var BlogBoxStruct|null $blogBoxStruct */
        $blogBoxStruct = $slot->getData();

        static::assertInstanceOf(BlogBoxStruct::class, $blogBoxStruct);

        $blog = $blogBoxStruct->getBlog();

        if ($closeout && $hidden && $availableStock === 0) {
            static::assertNull($blog);
        } else {
            static::assertNotNull($blog);
            static::assertSame($blogId, $blog->getId());
        }
    }

    /**
     * @return iterable<array<bool|int>> closeout, hidden, availableStock
     */
    public static function enrichDataProvider(): iterable
    {
        yield 'visible blog with stock is enriched' => [false, false, 1];
        yield 'hidden blog with stock is enriched when closeout is disabled' => [false, true, 1];
        yield 'closeout blog with stock is enriched when it is visible' => [true, false, 1];
        yield 'hidden closeout blog with stock is enriched' => [true, true, 1];
        yield 'hidden closeout blog without stock is not enriched' => [true, true, 0];
    }
}
