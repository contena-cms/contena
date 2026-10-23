<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Page\Navigation\NavigationPageLoadedHook;
use Contena\Frontend\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoadedHook;

/**
 * @internal
 */
class NavigationControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendControllerTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();
    }

    public function testNavigationPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/', []);
        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(NavigationPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testNavigationPageLoadedHookScriptsAreExecutedForCategory(): void
    {
        $response = $this->request('GET', '/my-navigation/', []);

        static::assertSame(200, $response->getStatusCode(), print_r($response->getContent(), true));

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(NavigationPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testMenuOffcanvasPageletLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/widgets/menu/offcanvas', []);
        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(MenuOffcanvasPageletLoadedHook::HOOK_NAME, $traces);
    }

    public function testFrontendRedirectsOutOfRangePaginationTo301(): void
    {
        // 5 blogs, default limit 24 → lastPage = 1; p=99 must redirect.
        $seoPath = $this->createCategoryWithBlogs(5);

        $response = $this->request('GET', $seoPath . '?p=99', []);

        static::assertSame(301, $response->getStatusCode());

        $location = $response->headers->get('Location');
        static::assertNotNull($location);
        static::assertStringNotContainsString('p=99', $location);
        static::assertStringNotContainsString('p=', $location);
    }

    public function testFrontendPreservesOtherQueryParamsOnRedirect(): void
    {
        $seoPath = $this->createCategoryWithBlogs(5);

        $response = $this->request('GET', $seoPath . '?p=99&order=price-asc', []);

        static::assertSame(301, $response->getStatusCode());

        $location = $response->headers->get('Location');
        static::assertNotNull($location);
        static::assertStringContainsString('order=price-asc', $location);
        static::assertStringNotContainsString('p=99', $location);
        static::assertStringNotContainsString('p=', $location);
    }

    public function testFrontendInRangePaginationStillReturns200AndRendersCanonicalUrl(): void
    {
        // 6 blogs, limit=2 → lastPage = 3; p=2 must succeed.
        $seoPath = $this->createCategoryWithBlogs(6);

        $response = $this->request('GET', $seoPath . '?p=2&limit=2', []);

        static::assertSame(200, $response->getStatusCode());

        $canonicalUrl = $this->extractCanonicalUrl((string) $response->getContent());
        static::assertStringEndsWith('/' . $seoPath . '?p=2', $canonicalUrl);

        $firstPageResponse = $this->request('GET', $seoPath . '?p=1&limit=2', []);

        static::assertSame(200, $firstPageResponse->getStatusCode());
        static::assertStringEndsWith('/' . $seoPath, $this->extractCanonicalUrl((string) $firstPageResponse->getContent()));
    }

    public function testOffcanvasBackLinkAtFooterRootReturnsToMainEntry(): void
    {
        $this->createFooterTree();

        $response = $this->request(
            'GET',
            'widgets/menu/offcanvas?navigationId=' . $this->ids->get('issue-13510-footer'),
            []
        );

        static::assertSame(200, $response->getStatusCode());

        $backLinkHref = $this->extractBackLinkHref((string) $response->getContent());

        static::assertStringNotContainsString('navigationId=', $backLinkHref);
    }

    public function testOffcanvasBackLinkAtFooterSubcategoryClimbsToParent(): void
    {
        $this->createFooterTree();

        $response = $this->request(
            'GET',
            'widgets/menu/offcanvas?navigationId=' . $this->ids->get('issue-13510-footer-about'),
            []
        );

        static::assertSame(200, $response->getStatusCode());

        $backLinkHref = $this->extractBackLinkHref((string) $response->getContent());

        static::assertStringContainsString(
            'navigationId=' . $this->ids->get('issue-13510-footer'),
            $backLinkHref
        );
    }

    private function createData(): void
    {
        $channel = static::getContainer()->get('channel.repository')->search(
            (new Criteria())->addFilter(
                new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND),
                new EqualsFilter('domains.url', $_SERVER['APP_URL'])
            ),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertInstanceOf(ChannelEntity::class, $channel);

        $category = [
            'id' => $this->ids->create('category'),
            'name' => 'my-navigation',
            'type' => 'landing_page',
            'parentId' => $channel->getNavigationCategoryId(),
        ];

        static::getContainer()->get('category.repository')->create([$category], Context::createDefaultContext());
    }

    /**
     * Creates a category with a blog-listing CMS page and $count simple blogs.
     * Returns the SEO path for the category (e.g. `listing-test-category/`).
     *
     * The SEO URL is queried from the database after creation so the path is always accurate.
     */
    private function createCategoryWithBlogs(int $count): string
    {
        $channelId = $this->getChannelId();

        /** @var ChannelEntity $channel */
        $channel = static::getContainer()->get('channel.repository')->search(
            new Criteria([$channelId]),
            Context::createDefaultContext()
        )->getEntities()->first();

        $categoryId = $this->ids->create('out-of-range-category');

        $blogs = [];
        for ($i = 0; $i < $count; ++$i) {
            $blogs[] = [
                'id' => $this->ids->create('out-of-range-blog-' . $i),
                'blogNumber' => $this->ids->get('out-of-range-blog-' . $i),
                'name' => 'Test blog ' . $i,
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['name' => 'tax-' . $i, 'taxRate' => 15],
                'manufacturer' => ['id' => $this->ids->create('out-of-range-manufacturer-' . $i), 'name' => 'manu-' . $i],
                'active' => true,
                'visibilities' => [
                    ['channelId' => $channelId, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
                'categories' => [['id' => $categoryId]],
            ];
        }

        static::getContainer()->get('category.repository')->create([[
            'id' => $categoryId,
            'name' => 'Out-of-range test category',
            'type' => 'page',
            'parentId' => $channel->getNavigationCategoryId(),
            'cmsPage' => [
                'id' => $this->ids->create('out-of-range-cms-page'),
                'type' => 'blog_list',
                'sections' => [[
                    'position' => 0,
                    'type' => 'sidebar',
                    'blocks' => [[
                        'type' => 'blog-listing',
                        'position' => 1,
                        'slots' => [
                            ['type' => 'blog-listing', 'slot' => 'content'],
                        ],
                    ]],
                ]],
            ],
        ]], Context::createDefaultContext());

        static::getContainer()->get('blog.repository')->create($blogs, Context::createDefaultContext());

        // Retrieve the SEO URL generated for this category so requests bypass
        // the technical-URL → SEO-URL redirect and land directly on the listing.
        /** @var SeoUrlEntity|null $seoUrl */
        $seoUrl = static::getContainer()->get('seo_url.repository')->search(
            (new Criteria())->addFilter(
                new EqualsFilter('foreignKey', $categoryId),
                new EqualsFilter('channelId', $channelId),
                new EqualsFilter('isCanonical', true)
            ),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertNotNull(
            $seoUrl,
            \sprintf('SEO URL for category %s was not generated; cannot exercise the frontend listing flow.', $categoryId)
        );

        return ltrim($seoUrl->getSeoPathInfo(), '/');
    }

    private function createFooterTree(): void
    {
        $channelId = $this->getChannelId();

        /** @var ChannelEntity $channel */
        $channel = static::getContainer()->get('channel.repository')->search(
            new Criteria([$channelId]),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::getContainer()->get('category.repository')->create([[
            'id' => $this->ids->create('issue-13510-intermediate'),
            'parentId' => $channel->getNavigationCategoryId(),
            'name' => 'Issue 13510 Intermediate',
            'type' => 'page',
            'active' => true,
            'visible' => true,
            'children' => [
                [
                    'id' => $this->ids->create('issue-13510-main'),
                    'name' => 'Issue 13510 Main',
                    'type' => 'page',
                    'active' => true,
                    'visible' => true,
                ],
                [
                    'id' => $this->ids->create('issue-13510-footer'),
                    'name' => 'Issue 13510 Footer',
                    'type' => 'page',
                    'active' => true,
                    'visible' => true,
                    'children' => [[
                        'id' => $this->ids->create('issue-13510-footer-about'),
                        'name' => 'Issue 13510 About',
                        'type' => 'page',
                        'active' => true,
                        'visible' => true,
                    ]],
                ],
            ],
        ]], Context::createDefaultContext());

        static::getContainer()->get('channel.repository')->update([[
            'id' => $channelId,
            'navigationCategoryId' => $this->ids->get('issue-13510-main'),
            'footerCategoryId' => $this->ids->get('issue-13510-footer'),
        ]], Context::createDefaultContext());
    }

    private function extractBackLinkHref(string $html): string
    {
        $matched = preg_match(
            '#<a[^>]*class="[^"]*\bis-back-link\b[^"]*"[^>]*href="([^"]+)"#',
            $html,
            $matches
        );

        static::assertSame(1, $matched, 'No back-link rendered in offcanvas response.');

        return html_entity_decode($matches[1]);
    }

    private function extractCanonicalUrl(string $html): string
    {
        $matched = preg_match('#<link rel="canonical" href="(.*)"#', $html, $matches);

        static::assertSame(1, $matched, 'No canonical link rendered in frontend response.');

        return html_entity_decode($matches[1]);
    }
}
