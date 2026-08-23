<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;

/**
 * @internal
 */
class NavigationControllerTest extends TestCase
{
    use FrontendControllerTestBehaviour;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();
    }

    public function testHomePageIsAvailable(): void
    {
        $response = $this->request('GET', '/', []);

        static::assertSame(200, $response->getStatusCode());
    }

    public function testCategoryPageIsAvailable(): void
    {
        $response = $this->request('GET', '/my-navigation/', []);

        static::assertSame(200, $response->getStatusCode(), print_r($response->getContent(), true));
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
            new Criteria()->addFilter(
                new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_WEB),
                new EqualsFilter('domains.url', $_SERVER['APP_URL'])
            ),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertInstanceOf(ChannelEntity::class, $channel);

        $contentLayoutId = static::getContainer()->get('content_layout.repository')->searchIds(
            new Criteria()->addFilter(new EqualsFilter('rootSource', 'category'))->setLimit(1),
            Context::createDefaultContext(),
        )->firstId();
        static::assertNotNull($contentLayoutId);

        $category = [
            'id' => $this->ids->create('category'),
            'name' => 'my-navigation',
            'type' => 'page',
            'active' => true,
            'visible' => true,
            'parentId' => $channel->getNavigationCategoryId(),
        ];

        static::getContainer()->get('category.repository')->create([$category], Context::createDefaultContext());
        static::getContainer()->get('category_content_layout.repository')->create([[
            'id' => $this->ids->create('category-layout-assignment'),
            'categoryId' => $category['id'],
            'channelId' => $channel->getId(),
            'contentLayoutId' => $contentLayoutId,
        ]], Context::createDefaultContext());
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
}
