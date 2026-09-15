<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\ContentSystem\Frontend;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexer;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexingMessage;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Contena\Tests\Integration\Core\Framework\ContentSystem\ContentLayoutFixtureBehaviour;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins the read leg of the card-grid presentation parameter: a persisted `content_layout` carrying a
 * `Ct:Blog:Listing` element is rendered through the real `frontend.content.layout` route, and the query
 * parameter that selects the horizontal presentation is `listingLayout`.
 *
 * Both directions are pinned by one request that seeds both keys with conflicting values, because that single
 * observation separates all three possible reads: reading `listingLayout` renders the horizontal presentation,
 * reading the superseded `layout` renders the default one, and reading neither also renders the default one.
 * Asserting the horizontal presentation therefore fails both on a lost read of the new key and on a
 * re-introduced read of the old one.
 *
 * It has to be one request, and that request has to be the first `app.request` read of its container.
 * `Contena\Frontend\Framework\Twig\TwigAppVariable::getRequest()` memoizes its cloned request in a private
 * property and carries neither a `reset()` nor a `kernel.reset` tag, and the integration harness reuses one
 * non-rebooting kernel for the whole process (`FrontendControllerTestBehaviour::request()` →
 * `KernelLifecycleManager::createBrowser()` with reboot disabled), so the first render in the process that
 * reads `app.request` fixes it for every later render — neither `services_resetter` nor `clearRequestStack()`
 * clears it. A second request here would be asserted against the first request's query string, and any earlier
 * `app.request` reader in the same process (any test that renders through `base.html.twig`, so most of
 * `tests/integration/Frontend/`) would supply its own query string to this one. `setUpBeforeClass()`
 * therefore boots a fresh kernel, whose container carries a fresh, unarmed decorator.
 *
 * `strict_variables` is false, so a template member that stops resolving renders empty and the route still
 * answers 200; a status assertion proves nothing on its own. The assertions therefore address concrete
 * rendered nodes — the one grid container and the per-blog card roots — and read their presentation
 * classes, which are the two consumers of the parameter inside `Ct/Blog/Listing.html.twig`. The response
 * body is passed as the assertion message so a blank or restructured render is readable.
 *
 * @internal
 */
class ListingLayoutQueryParameterRenderTest extends TestCase
{
    use ContentLayoutFixtureBehaviour;
    use FrontendChannelTestHelper;
    use FrontendControllerTestBehaviour;
    use IntegrationTestBehaviour;

    private const BLOG_COUNT = 2;

    /**
     * The card roots, addressed by whole class token so the nested `ct-blog-card__*` wrappers are excluded.
     */
    private const CARD_XPATH = '//div[contains(concat(" ", normalize-space(@class), " "), " ct-blog-card ")]';

    private const GRID_XPATH = '//div[contains(concat(" ", normalize-space(@class), " "), " ct-blog-listing__grid ")]/div[contains(concat(" ", normalize-space(@class), " "), " ct-grid-container__inner ")]';

    private IdsCollection $ids;

    private string $channelId;

    /**
     * A fresh container carries a fresh `TwigAppVariable`, so this class's render is the first `app.request`
     * read of a memo the harness never resets. Before the transaction hook, so no open transaction is dropped.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        KernelLifecycleManager::bootKernel();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->createTestCategory($this->ids->create('category'), 'Listing layout category');
        $this->createWebChannelType();
        $this->createFallbackMemberGroup();
        $this->createDefaultBlogSeoUrlTemplate();
        $this->prepareFrontendChannel();
        $this->createBlogs();
        $this->persistLayout();
    }

    #[TestDox('reads the card grid presentation from listingLayout and not from the superseded layout key')]
    public function testListingLayoutParameterIsReadAndTheSupersededOneIsNot(): void
    {
        $html = $this->render(['layout' => 'default', 'listingLayout' => 'horizontal']);

        static::assertSame(
            ['is--layout-horizontal', 'is--layout-horizontal'],
            $this->cardLayoutClasses($html),
            $html
        );
        static::assertSame(
            ['columns-1', 'columns-lg-1', 'columns-md-1', 'columns-sm-1', 'columns-xl-1'],
            $this->gridColumnClasses($html),
            $html
        );
    }

    /**
     * @param array<string, string> $query
     */
    private function render(array $query): string
    {
        $response = $this->request('GET', 'content/category/' . $this->ids->get('category'), $query);

        $html = (string) $response->getContent();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $html);

        return $html;
    }

    /**
     * The presentation class of every rendered card, in document order.
     *
     * @return list<string>
     */
    private function cardLayoutClasses(string $html): array
    {
        $cards = $this->query($html, self::CARD_XPATH);
        static::assertCount(self::BLOG_COUNT, $cards, $html);

        $classes = [];

        foreach ($cards as $card) {
            static::assertInstanceOf(\DOMElement::class, $card);

            foreach ($this->classTokens($card) as $token) {
                if (str_starts_with($token, 'is--layout-')) {
                    $classes[] = $token;
                }
            }
        }

        return $classes;
    }

    /**
     * The column classes of the one inner grid, sorted so the assertion does not pin the CVA emission order.
     *
     * @return list<string>
     */
    private function gridColumnClasses(string $html): array
    {
        $grids = $this->query($html, self::GRID_XPATH);
        static::assertCount(1, $grids, $html);

        $grid = $grids->item(0);
        static::assertInstanceOf(\DOMElement::class, $grid);

        $columns = array_values(array_filter(
            $this->classTokens($grid),
            static fn (string $token): bool => str_starts_with($token, 'columns-')
        ));

        sort($columns);

        return $columns;
    }

    /**
     * @return \DOMNodeList<\DOMNameSpaceNode|\DOMNode>
     */
    private function query(string $html, string $expression): \DOMNodeList
    {
        $document = new \DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        static::assertTrue($loaded);

        $nodes = new \DOMXPath($document)->query($expression);
        static::assertInstanceOf(\DOMNodeList::class, $nodes);

        return $nodes;
    }

    /**
     * @return list<string>
     */
    private function classTokens(\DOMElement $element): array
    {
        $tokens = preg_split('/\s+/', trim($element->getAttribute('class')), -1, \PREG_SPLIT_NO_EMPTY);
        static::assertIsArray($tokens);

        return $tokens;
    }

    private function persistLayout(): void
    {
        $this->persistContentLayout($this->ids->create('layout'), 'listing-layout-parameter', '1.0.0', 'category', [[
            'id' => $this->ids->create('listing'),
            'component' => 'Ct:Blog:Listing',
            'acceptsContext' => [
                'blogListing' => [
                    'type' => 'single',
                    'required' => true,
                    'propertyAlias' => 'listing',
                    'scope' => 'root',
                ],
            ],
        ]]);

        $this->assignLayoutToCategory(
            $this->ids->create('assignment'),
            $this->ids->get('category'),
            $this->ids->get('layout'),
        );
    }

    private function createBlogs(): void
    {
        $blogs = [];
        $blogIds = [];

        for ($index = 0; $index < self::BLOG_COUNT; ++$index) {
            $blogId = $this->ids->create('blog-' . $index);
            $blogIds[] = $blogId;
            $blogs[] = [
                'id' => $blogId,
                'name' => 'Listing layout blog ' . $index,
                'active' => true,
                'type' => BlogDefinition::TYPE_POST,
                'categories' => [['id' => $this->ids->get('category')]],
                'visibilities' => [[
                    'channelId' => $this->channelId,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ]],
            ];
        }

        $context = Context::createDefaultContext();
        $this->repository('blog.repository')->create($blogs, $context);
        static::getContainer()->get(BlogIndexer::class)->handle(new BlogIndexingMessage($blogIds, $context));
    }

    private function prepareFrontendChannel(): void
    {
        $domainRepository = $this->repository('channel_domain.repository');
        $context = Context::createDefaultContext();

        $domain = $domainRepository->search(
            new Criteria()->addFilter(new EqualsFilter('url', $_SERVER['APP_URL']))->setLimit(1),
            $context
        )->getEntities()->first();

        if ($domain instanceof ChannelDomainEntity) {
            $this->channelId = $domain->getChannelId();
            $this->updateChannelNavigationEntryPoint($this->channelId, $this->ids->get('category'));

            return;
        }

        $this->channelId = $this->ids->create('channel');
        $this->createFrontendChannelContext(
            $this->channelId,
            'listing-layout',
            categoryEntrypoint: $this->ids->get('category'),
        );

        $domainId = $domainRepository->searchIds(
            new Criteria()->addFilter(new EqualsFilter('channelId', $this->channelId))->setLimit(1),
            $context
        )->firstId();
        static::assertNotNull($domainId);

        $domainRepository->update([[
            'id' => $domainId,
            'url' => $_SERVER['APP_URL'],
        ]], $context);
    }

    private function createFallbackMemberGroup(): void
    {
        $this->repository('member_group.repository')->upsert([[
            'id' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'name' => 'Listing layout member group',
        ]], Context::createDefaultContext());
    }

    private function createWebChannelType(): void
    {
        $this->repository('channel_type.repository')->upsert([[
            'id' => Defaults::CHANNEL_TYPE_WEB,
            'name' => 'Web',
            'manufacturer' => 'Contena',
        ]], Context::createDefaultContext());
    }

    private function createDefaultBlogSeoUrlTemplate(): void
    {
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('routeName', BlogPageSeoUrlRoute::ROUTE_NAME))
            ->addFilter(new EqualsFilter('channelId', null));

        if ($this->repository('seo_url_template.repository')->searchIds($criteria, Context::createDefaultContext())->firstId() !== null) {
            return;
        }

        $this->repository('seo_url_template.repository')->create([[
            'id' => $this->ids->create('blog-seo-url-template'),
            'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => BlogDefinition::ENTITY_NAME,
            'template' => BlogPageSeoUrlRoute::DEFAULT_TEMPLATE,
            'isHeadless' => false,
        ]], Context::createDefaultContext());
    }
}
