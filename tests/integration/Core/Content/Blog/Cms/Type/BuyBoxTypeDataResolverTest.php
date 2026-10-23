<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Cms\Type;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Detail\BlogConfiguratorLoader;
use Contena\Core\Content\Blog\Cms\BuyBoxCmsElementResolver;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BuyBoxStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class BuyBoxTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private BuyBoxCmsElementResolver $buyBoxResolver;

    protected function setUp(): void
    {
        $saleChannelBlogEntity = new ChannelBlogEntity();
        $saleChannelBlogEntity->setId('blog123');
        $mockConfiguratorLoader = static::createStub(BlogConfiguratorLoader::class);
        $mockConfiguratorLoader->method('load')->willReturn(
            new PropertyGroupCollection()
        );

        $repositoryMock = static::createStub(EntityRepository::class);

        $this->buyBoxResolver = new BuyBoxCmsElementResolver($mockConfiguratorLoader, $repositoryMock);
    }

    public function testGetType(): void
    {
        static::assertSame('buy-box', $this->buyBoxResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->buyBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, 'blog123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->buyBoxResolver->collect($slot, $resolverContext);

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

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->buyBoxResolver->enrich($slot, $resolverContext, $result);

        $buyBoxStruct = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $buyBoxStruct);
        static::assertNull($buyBoxStruct->getBlogId());
        static::assertNull($buyBoxStruct->getBlog());
    }

    public function testEnrichWithStaticConfig(): void
    {
        $blog = new ChannelBlogEntity();
        $blog->setId('blog123');
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
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
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, 'blog123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig($fieldConfig);

        $this->buyBoxResolver->enrich($slot, $resolverContext, $result);

        $buyBoxStruct = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $buyBoxStruct);
        static::assertSame($blog->getId(), $buyBoxStruct->getBlogId());
    }

    public function testCollectWithEmptyBlogId(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->buyBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testReviewCountLoaded(): void
    {
        $blogId = Uuid::randomHex();
        $saleChannelContext = $this->createChannelContext();

        $this->createBlog($blogId);
        $this->createReviews($blogId);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, $blogId));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig($fieldConfig);

        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $resolverContext = new ResolverContext($saleChannelContext, new Request());

        $result = new ElementDataCollection();
        $result->add('blog_id', new EntitySearchResult(
            'blog',
            1,
            new BlogCollection([$blog]),
            null,
            new Criteria(),
            $resolverContext->getChannelContext()->getContext()
        ));

        $buyBoxResolver = static::getContainer()->get(BuyBoxCmsElementResolver::class);
        $buyBoxResolver->enrich($slot, $resolverContext, $result);

        $buyBoxStruct = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $buyBoxStruct);

        static::assertSame(3, $buyBoxStruct->getTotalReviews());
    }

    #[DataProvider('reviewCountDataProvider')]
    public function testReviewCountLoadedWithVariants(int $variantCount, int $reviewsPerBlog, int $expectedReviews): void
    {
        $blogId = Uuid::randomHex();
        $variantIds = [];
        $channelContext = $this->createChannelContext();

        $this->createBlog($blogId);
        $this->createReviews($blogId, $reviewsPerBlog);
        for ($i = 0; $i < $variantCount; ++$i) {
            $variantIds[$i] = Uuid::randomHex();
            $this->createBlog($variantIds[$i], ['parentId' => $blogId]);
            $this->createReviews($variantIds[$i], $reviewsPerBlog);
        }
        $investigatedBlogId = $variantIds[0] ?? $blogId;

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_MAPPED, $investigatedBlogId));
        $fieldConfig->add(new FieldConfig('alignment', FieldConfig::SOURCE_STATIC, null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig($fieldConfig);

        $variantBlog = new ChannelBlogEntity();
        $variantBlog->setId($investigatedBlogId);
        if (isset($variantIds[0])) {
            $variantBlog->setParentId($blogId);
        }
        $variantBlog->setOptionIds([Uuid::randomHex()]);

        $resolverContext = new EntityResolverContext(
            $channelContext,
            new Request(),
            static::createStub(ChannelBlogDefinition::class),
            $variantBlog
        );

        $result = new ElementDataCollection();

        $buyBoxResolver = static::getContainer()->get(BuyBoxCmsElementResolver::class);
        $buyBoxResolver->enrich($slot, $resolverContext, $result);

        $buyBoxStruct = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $buyBoxStruct);

        static::assertSame($expectedReviews, $buyBoxStruct->getTotalReviews());
    }

    /**
     * @return iterable<string, list<int>>
     */
    public static function reviewCountDataProvider(): iterable
    {
        // variant count, reviews per variant, expected review count
        yield 'No variants, No reviews' => [0, 0, 0];
        yield 'No variants, 3 reviews' => [0, 3, 3];
        yield 'No variants, 10 reviews' => [0, 10, 10];

        yield 'One variant, No reviews' => [1, 0, 0];
        yield 'One variant, 3 reviews each' => [1, 3, 6];
        yield 'One variant, 10 reviews each' => [1, 10, 20];

        yield '5 variants, No reviews' => [5, 0, 0];
        yield '5 variants, 3 reviews each' => [5, 3, 18];
        yield '5 variants, 10 reviews each' => [5, 10, 60];
    }

    /**
     * @param array<string, mixed> $additionalData
     */
    private function createBlog(string $blogId, array $additionalData = []): void
    {
        $data = array_merge([
            'id' => $blogId,
            'name' => 'test',
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'purchasePrice' => 7.5,
            'purchasePrices' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 7.5, 'net' => 5, 'linked' => false],
                ['currencyId' => Uuid::randomHex(), 'gross' => 150, 'net' => 100, 'linked' => false],
            ],
            'active' => true,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'weight' => 100,
            'height' => 101,
            'width' => 102,
            'length' => 103,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'test',
                ],
            ],
        ], $additionalData);

        static::getContainer()->get('blog.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function createReviews(string $blogId, int $reviewCount = 3): void
    {
        $reviews = [];
        for ($i = 1; $i <= $reviewCount; ++$i) {
            $reviews[] = [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'channelId' => TestDefaults::CHANNEL,
                'blogId' => $blogId,
                'title' => 'Test',
                'content' => 'test',
                'externalUser' => 'Test',
                'points' => min(5, $i + $i / 5),
                'status' => true,
            ];
        }

        static::getContainer()->get('blog_review.repository')
            ->create($reviews, Context::createDefaultContext());
    }

    private function createChannelContext(): ChannelContext
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);

        return $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }
}
