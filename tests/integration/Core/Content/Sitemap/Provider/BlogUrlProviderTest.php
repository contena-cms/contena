<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Sitemap\Provider;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Sitemap\Provider\BlogUrlProvider;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\Tax\TaxEntity;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class BlogUrlProviderTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;
    use FrontendChannelTestHelper;

    private const CONFIG_EXCLUDE_LINKED_BLOGS = 'core.sitemap.excludeLinkedBlogs';

    private const CONFIG_HIDE_AFTER_CLOSEOUT = 'core.listing.hideCloseoutBlogsWhenOutOfStock';

    private ChannelContext $channelContext;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        if (!static::getContainer()->has(BlogPageSeoUrlRoute::class)) {
            static::markTestSkipped('NEXT-16799: Sitemap module has a dependency on frontend routes');
        }

        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->seoUrlPlaceholderHandler = static::getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);

        $this->channelContext = $this->createFrontendChannelContext(Uuid::randomHex(), 'test-blog-sitemap');
    }

    public function testBlogUrlObjectContainsValidContent(): void
    {
        $blogs = $this->createBlogs();

        $urlResult = $this->getBlogUrlProvider()->getUrls($this->channelContext, 5);

        $urls = $urlResult->getUrls();

        $firstUrl = $urls[0];

        static::assertSame('hourly', $firstUrl->getChangefreq());
        static::assertSame(0.5, $firstUrl->getPriority());
        static::assertSame(BlogEntity::class, $firstUrl->getResource());
        static::assertTrue(Uuid::isValid($firstUrl->getIdentifier()));

        $host = $this->getHost($this->channelContext);

        foreach ($blogs as $blog) {
            $urlGenerate = $this->getComparisonUrl($blog['id']);
            $check = false;
            foreach ($urls as $url) {
                if ($urlGenerate === $host . '/' . $url->getLoc()) {
                    $check = true;

                    break;
                }
            }
            static::assertTrue($check);
        }
    }

    public function testReturnedOffsetIsValid(): void
    {
        $this->createBlogs();

        $blogUrlProvider = $this->getBlogUrlProvider();

        // first run
        $urlResult = $blogUrlProvider->getUrls($this->channelContext, 3);
        static::assertIsNumeric($urlResult->getNextOffset());

        // 1+n run
        $urlResult = $blogUrlProvider->getUrls($this->channelContext, 2, $urlResult->getNextOffset());
        static::assertIsNumeric($urlResult->getNextOffset());

        // last run
        $urlResult = $blogUrlProvider->getUrls($this->channelContext, 100, $urlResult->getNextOffset()); // test with high number to get last chunk
        static::assertNull($urlResult->getNextOffset());
    }

    public function testOnlyVariantUrlsGenerated(): void
    {
        $parentId = Uuid::randomHex();
        $blogs = [
            array_merge([
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test parent 1',
            ], $this->getBasicBlogData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'parentId' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test variant 1',
            ], $this->getBasicBlogData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'parentId' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test variant 2',
            ], $this->getBasicBlogData()),
        ];
        $this->blogRepository->create($blogs, $this->channelContext->getContext());

        $urlResult = $this->getBlogUrlProvider()->getUrls($this->channelContext, 3);
        $host = $this->getHost($this->channelContext);
        $locations = array_map(static fn ($url) => $host . '/' . $url->getLoc(), $urlResult->getUrls());

        foreach ($blogs as $blog) {
            $urlGenerate = $this->getComparisonUrl($blog['id']);
            if ($blog['id'] === $parentId) {
                static::assertNotContains($urlGenerate, $locations);
            } else {
                static::assertContains($urlGenerate, $locations);
            }
        }
    }

    public function testOnlyCanonicalVariantUrlGenerated(): void
    {
        $parentId = Uuid::randomHex();
        $canonicalBlogId = Uuid::randomHex();
        $blogs = [
            array_merge([
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test parent 2',
            ], $this->getBasicBlogData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'parentId' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test variant 3',
            ], $this->getBasicBlogData()),
            array_merge([
                'id' => $canonicalBlogId,
                'parentId' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test variant canonical',
            ], $this->getBasicBlogData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'parentId' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test variant 4',
            ], $this->getBasicBlogData()),
        ];
        $this->blogRepository->create($blogs, $this->channelContext->getContext());
        $this->blogRepository->update(
            [['id' => $parentId, 'canonicalBlogId' => $canonicalBlogId]],
            $this->channelContext->getContext()
        );

        $urlResult = $this->getBlogUrlProvider()->getUrls($this->channelContext, 4);
        $urls = $urlResult->getUrls();

        static::assertCount(1, $urls);

        $host = $this->getHost($this->channelContext);
        $urlGenerate = $this->getComparisonUrl($canonicalBlogId);
        static::assertSame($urlGenerate, $host . '/' . $urls[0]->getLoc());
    }

    public function testContainsOutOfStockCloseoutBlogs(): void
    {
        $this->systemConfigService->set(self::CONFIG_HIDE_AFTER_CLOSEOUT, false, $this->channelContext->getChannelId());
        $this->createCloseoutBlogs();

        $urlResult = $this->getBlogUrlProvider()->getUrls($this->channelContext, 2);

        static::assertCount(2, $urlResult->getUrls());
    }

    public function testContainsNoOutOfStockCloseoutBlogs(): void
    {
        $this->systemConfigService->set(self::CONFIG_HIDE_AFTER_CLOSEOUT, true, $this->channelContext->getChannelId());
        $this->createCloseoutBlogs();

        $urlResult = $this->getBlogUrlProvider()->getUrls($this->channelContext, 2);

        static::assertCount(1, $urlResult->getUrls());
    }

    public function testContainsOutOfStockVariantsInheritingCloseout(): void
    {
        $this->systemConfigService->set(self::CONFIG_HIDE_AFTER_CLOSEOUT, false, $this->channelContext->getChannelId());
        [$outOfStockVariantId, $inStockVariantId] = $this->createVariantsInheritingCloseout();

        $locations = $this->getLocations(3);

        static::assertCount(2, $locations);
        static::assertContains($this->getComparisonUrl($outOfStockVariantId), $locations);
        static::assertContains($this->getComparisonUrl($inStockVariantId), $locations);
    }

    public function testContainsNoOutOfStockVariantsInheritingCloseout(): void
    {
        $this->systemConfigService->set(self::CONFIG_HIDE_AFTER_CLOSEOUT, true, $this->channelContext->getChannelId());
        [$outOfStockVariantId, $inStockVariantId] = $this->createVariantsInheritingCloseout();

        $locations = $this->getLocations(3);

        static::assertSame([$this->getComparisonUrl($inStockVariantId)], $locations);
        static::assertNotContains($this->getComparisonUrl($outOfStockVariantId), $locations);
    }

    public function testContainsOutOfStockCanonicalVariantInheritingCloseout(): void
    {
        $this->systemConfigService->set(self::CONFIG_HIDE_AFTER_CLOSEOUT, false, $this->channelContext->getChannelId());
        [$outOfStockVariantId, , $parentId] = $this->createVariantsInheritingCloseout();
        $this->blogRepository->update(
            [['id' => $parentId, 'canonicalBlogId' => $outOfStockVariantId]],
            $this->channelContext->getContext()
        );

        static::assertSame([$this->getComparisonUrl($outOfStockVariantId)], $this->getLocations(3));
    }

    public function testContainsHiddenBlogs(): void
    {
        $this->systemConfigService->set(self::CONFIG_EXCLUDE_LINKED_BLOGS, false, $this->channelContext->getChannelId());
        $this->createHiddenVisibilityBlog();

        $urlResult = $this->getBlogUrlProvider()->getUrls($this->channelContext, 1);

        static::assertCount(1, $urlResult->getUrls());
    }

    public function testContainsNoHiddenBlogs(): void
    {
        $this->systemConfigService->set(self::CONFIG_EXCLUDE_LINKED_BLOGS, true, $this->channelContext->getChannelId());
        $this->createHiddenVisibilityBlog();

        $urlResult = $this->getBlogUrlProvider()->getUrls($this->channelContext, 1);

        static::assertCount(0, $urlResult->getUrls());
    }

    private function getBlogUrlProvider(): BlogUrlProvider
    {
        return static::getContainer()->get(BlogUrlProvider::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function createBlogs(): array
    {
        $blogs = $this->getBlogTestData();

        static::getContainer()->get('blog.repository')->create($blogs, $this->channelContext->getContext());

        return $blogs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getBlogTestData(): array
    {
        $blogs = [
            array_merge([
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test blog 1',
            ], $this->getBasicBlogData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test blog 2',
            ], $this->getBasicBlogData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test blog 3',
            ], $this->getBasicBlogData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test blog 4',
            ], $this->getBasicBlogData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test blog 5',
            ], $this->getBasicBlogData()),
        ];

        return $blogs;
    }

    private function getHost(ChannelContext $context): string
    {
        $domains = $context->getChannel()->getDomains();
        $languageId = $context->getLanguageId();

        if ($domains instanceof ChannelDomainCollection) {
            foreach ($domains as $domain) {
                if ($domain->getLanguageId() === $languageId) {
                    return $domain->getUrl();
                }
            }
        }

        throw new \RuntimeException('Empty domain');
    }

    private function getComparisonUrl(string $blogId): string
    {
        $loc = $this->seoUrlPlaceholderHandler->generate(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => $blogId]);

        return $this->seoUrlPlaceholderHandler->replace($loc, $this->getHost($this->channelContext), $this->channelContext);
    }

    /**
     * @return array<string, mixed>
     */
    private function getBasicBlogData(): array
    {
        $first = $this->channelContext->getTaxRules()->first();
        static::assertInstanceOf(TaxEntity::class, $first);

        $taxId = $first->getId();

        return [
            'stock' => 100,
            'type' => BlogDefinition::TYPE_PHYSICAL,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'tax' => ['id' => $taxId],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                ['channelId' => $this->channelContext->getChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
    }

    private function createCloseoutBlogs(): void
    {
        $blogs = [
            array_merge($this->getBasicBlogData(), [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test 1',
                'isCloseout' => true,
            ]),
            array_merge($this->getBasicBlogData(), [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test 2',
                'isCloseout' => true,
                'stock' => 0,
            ]),
        ];
        $this->blogRepository->create($blogs, $this->channelContext->getContext());
    }

    /**
     * @return array{string, string, string}
     */
    private function createVariantsInheritingCloseout(): array
    {
        $parentId = Uuid::randomHex();
        $outOfStockVariantId = Uuid::randomHex();
        $inStockVariantId = Uuid::randomHex();

        $this->blogRepository->create([
            array_merge($this->getBasicBlogData(), [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'closeout parent',
                'isCloseout' => true,
            ]),
            array_merge($this->getBasicBlogData(), [
                'id' => $outOfStockVariantId,
                'parentId' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 0,
            ]),
            array_merge($this->getBasicBlogData(), [
                'id' => $inStockVariantId,
                'parentId' => $parentId,
                'blogNumber' => Uuid::randomHex(),
            ]),
        ], $this->channelContext->getContext());

        static::assertSame(
            ['is_closeout' => null, 'available' => '0'],
            static::getContainer()->get(Connection::class)->fetchAssociative(
                'SELECT is_closeout, available FROM blog WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($outOfStockVariantId)]
            )
        );

        return [$outOfStockVariantId, $inStockVariantId, $parentId];
    }

    /**
     * @return array<string>
     */
    private function getLocations(int $limit): array
    {
        $host = $this->getHost($this->channelContext);

        return array_map(
            static fn ($url) => $host . '/' . $url->getLoc(),
            $this->getBlogUrlProvider()->getUrls($this->channelContext, $limit)->getUrls()
        );
    }

    private function createHiddenVisibilityBlog(): void
    {
        $blogs = [
            array_merge($this->getBasicBlogData(), [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test 1',
                'visibilities' => [
                    [
                        'channelId' => $this->channelContext->getChannelId(),
                        'visibility' => BlogVisibilityDefinition::VISIBILITY_LINK,
                    ],
                ],
            ]),
        ];
        $this->blogRepository->create($blogs, $this->channelContext->getContext());
    }
}
