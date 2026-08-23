<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Sitemap\Provider;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Sitemap\Provider\BlogUrlProvider;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;

/**
 * @internal
 */
class BlogUrlProviderTest extends TestCase
{
    use AdminApiTestBehaviour;
    use FrontendChannelTestHelper;
    use IntegrationTestBehaviour;

    private const CONFIG_EXCLUDE_LINKED_BLOGS = 'core.sitemap.excludeLinkedBlogs';

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
                'name' => 'test blog 1',
            ], $this->getBasicBlogData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'name' => 'test blog 2',
            ], $this->getBasicBlogData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'name' => 'test blog 3',
            ], $this->getBasicBlogData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'name' => 'test blog 4',
            ], $this->getBasicBlogData()),
            array_merge([
                'id' => Uuid::randomHex(),
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
        return [
            'active' => true,
            'visibilities' => [
                ['channelId' => $this->channelContext->getChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
    }

    private function createHiddenVisibilityBlog(): void
    {
        $blogs = [
            array_merge($this->getBasicBlogData(), [
                'id' => Uuid::randomHex(),
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
