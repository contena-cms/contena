<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Review\AbstractBlogReviewRoute;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewLoader;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewResult;
use Contena\Core\Content\Blog\Cms\BlogDescriptionReviewsCmsElementResolver;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BlogDescriptionReviewsStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogDescriptionReviewsCmsElementResolver::class)]
class BlogDescriptionReviewsCmsElementResolverTest extends TestCase
{
    private StaticSystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService([
            'core.listing.showReview' => true,
        ]);
    }

    public function testGetType(): void
    {
        $resolver = $this->getResolver();

        static::assertSame('blog-description-reviews', $resolver->getType());
    }

    public function testCollect(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType(BlogDescriptionReviewsCmsElementResolver::TYPE);

        $collection = $this->getResolver()->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichSlotWithBlogDescriptionReviews(): void
    {
        // global setting enabled
        $this->systemConfigService->set('core.listing.showReview', true);

        $resolver = $this->getResolver();

        $context = new ResolverContext(Generator::generateChannelContext(), new Request([
            'success' => true,
        ]));

        $blogId = 'blog-1';
        $config = new FieldConfigCollection([
            new FieldConfig('blog', FieldConfig::SOURCE_STATIC, $blogId),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ChannelBlogCollection([$blog]));

        $data = new ElementDataCollection();
        $data->add('blog_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(BlogDescriptionReviewsStruct::class, $data);
        static::assertTrue($data->getRatingSuccess());

        $reviews = $data->getReviews();
        static::assertInstanceOf(BlogReviewResult::class, $reviews);
        static::assertSame($blog, $data->getBlog());
        static::assertSame($blogId, $reviews->getBlogId());
    }

    public function testEnrichSlotWithBlogDescriptionReviewsForCurrentChannel(): void
    {
        $resolver = $this->getResolver();

        $context = Generator::generateChannelContext();

        // global setting disabled
        $this->systemConfigService->set('core.listing.showReview', false);
        // but enabled for current sales channel
        $this->systemConfigService->set('core.listing.showReview', true, $context->getChannelId());

        $context = new ResolverContext($context, new Request([
            'success' => true,
        ]));

        $blogId = 'blog-1';
        $config = new FieldConfigCollection([
            new FieldConfig('blog', FieldConfig::SOURCE_STATIC, $blogId),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ChannelBlogCollection([$blog]));

        $data = new ElementDataCollection();
        $data->add('blog_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(BlogDescriptionReviewsStruct::class, $data);
        static::assertTrue($data->getRatingSuccess());

        $reviews = $data->getReviews();
        static::assertInstanceOf(BlogReviewResult::class, $reviews);
        static::assertSame($blog, $data->getBlog());
        static::assertSame($blogId, $reviews->getBlogId());
    }

    public function testEnrichSlotWithBlogReviewsDisabled(): void
    {
        $resolver = $this->getResolver();

        $this->systemConfigService->set('core.listing.showReview', false);

        $context = new ResolverContext(Generator::generateChannelContext(), new Request([
            'success' => true,
        ]));

        $blogId = 'blog-1';
        $config = new FieldConfigCollection([
            new FieldConfig('blog', FieldConfig::SOURCE_STATIC, $blogId),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ChannelBlogCollection([$blog]));

        $data = new ElementDataCollection();
        $data->add('blog_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(BlogDescriptionReviewsStruct::class, $data);
        static::assertTrue($data->getRatingSuccess());

        static::assertNull($data->getReviews());
        static::assertSame($blog, $data->getBlog());
    }

    public function testEnrichSlotWithBlogReviewsDisabledForCurrentChannel(): void
    {
        $resolver = $this->getResolver();

        $channelContext = Generator::generateChannelContext();
        $channelId = $channelContext->getChannelId();

        $context = new ResolverContext($channelContext, new Request([
            'success' => true,
        ]));

        // global setting enabled
        $this->systemConfigService->set('core.listing.showReview', true);
        // but disabled for current sales channel
        $this->systemConfigService->set('core.listing.showReview', false, $channelId);

        $blogId = 'blog-1';
        $config = new FieldConfigCollection([
            new FieldConfig('blog', FieldConfig::SOURCE_STATIC, $blogId),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ChannelBlogCollection([$blog]));

        $data = new ElementDataCollection();
        $data->add('blog_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(BlogDescriptionReviewsStruct::class, $data);
        static::assertTrue($data->getRatingSuccess());

        static::assertNull($data->getReviews());
        static::assertSame($blog, $data->getBlog());
    }

    public function testEnrichSetsEmptyDataWithoutConfig(): void
    {
        $resolver = $this->getResolver();

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $data = new ElementDataCollection();

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(BlogDescriptionReviewsStruct::class, $data);
        static::assertNull($data->getReviews());
        static::assertNull($data->getBlog());
    }

    private function getResolver(): BlogDescriptionReviewsCmsElementResolver
    {
        $blogReviewLoader = new BlogReviewLoader(
            static::createStub(AbstractBlogReviewRoute::class),
            $this->systemConfigService,
            new EventDispatcher()
        );

        $scriptExecutor = static::createStub(ScriptExecutor::class);

        return new BlogDescriptionReviewsCmsElementResolver($blogReviewLoader, $scriptExecutor, $this->systemConfigService);
    }
}
