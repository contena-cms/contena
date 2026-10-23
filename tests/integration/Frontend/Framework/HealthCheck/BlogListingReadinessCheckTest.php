<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\CartRuleLoader;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelEntityLoadedEvent;
use Contena\Core\System\Channel\Event\ChannelProcessCriteriaEvent;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\SystemCheck\BlogListingReadinessCheck;

/**
 * @internal
 */
class BlogListingReadinessCheckTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use ChannelApiTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->channelRepository = static::getContainer()->get('channel.repository');

        $this->createChannels();
    }

    public function testCheckBlogListing(): void
    {
        $this->createMainNavigationWithChannelAssignment($this->ids->get('sales-channel-1'), true);
        $this->createMainNavigationWithChannelAssignment($this->ids->get('sales-channel-2'), false);

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
    }

    public function testCheckBlogListingWithoutBlogs(): void
    {
        $this->createMainNavigationWithoutBlogs();

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
    }

    public function testCheckIsHealthyWhenAllListingCategoriesAreRestrictedByAnExtension(): void
    {
        $categoryIds = [
            $this->createMainNavigationWithChannelAssignment($this->ids->get('sales-channel-1'), true),
            $this->createMainNavigationWithChannelAssignment($this->ids->get('sales-channel-2'), false),
        ];

        // simulates an extension that restricts category visibility by rules, e.g. Dynamic Access:
        // for an anonymous visitor the rule does not match, so the categories are filtered out
        $this->restrictCategories(array_merge(...$categoryIds));

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::SKIPPED, $result->status);
    }

    /**
     * Dynamic Access does not filter the category criteria, it deactivates restricted categories after they are
     * loaded (`channel.category.loaded`). The frontend refuses to render them, so the check must not
     * probe them either.
     */
    public function testCheckIsHealthyWhenAllListingCategoriesAreDeactivatedOnLoad(): void
    {
        $categoryIds = array_merge(
            $this->createMainNavigationWithChannelAssignment($this->ids->get('sales-channel-1'), true),
            $this->createMainNavigationWithChannelAssignment($this->ids->get('sales-channel-2'), false),
        );

        EventHookDispatcher::fromContainer(static::getContainer())->on(
            'channel.category.loaded',
            static function (ChannelEntityLoadedEvent $event) use ($categoryIds): void {
                foreach ($event->getEntities() as $category) {
                    if ($category instanceof CategoryEntity && \in_array($category->getId(), $categoryIds, true)) {
                        $category->setActive(false);
                    }
                }
            }
        );

        $result = $this->createCheck()->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::SKIPPED, $result->status);
    }

    /**
     * Extensions like Dynamic Access can also expose a category only for a rule that matches an anonymous
     * visitor. The probe request detects those rules, so the lookup has to as well, otherwise the check
     * reports SKIPPED while the listing pages render fine.
     */
    public function testCategoriesRestrictedToARuleMatchingAnAnonymousVisitorAreProbed(): void
    {
        $categoryIds = [
            $this->createMainNavigationWithChannelAssignment($this->ids->get('sales-channel-1'), true),
            $this->createMainNavigationWithChannelAssignment($this->ids->get('sales-channel-2'), false),
        ];

        $this->restrictCategories(array_merge(...$categoryIds), unlessRuleId: $this->createAlwaysValidRule());

        $result = $this->createCheck()->run();

        static::assertSame(Status::OK, $result->status);
        static::assertCount(2, $result->extra);
    }

    /**
     * @param list<string> $categoryIds
     */
    private function restrictCategories(array $categoryIds, ?string $unlessRuleId = null): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            'channel.category.process.criteria',
            static function (ChannelProcessCriteriaEvent $event) use ($categoryIds, $unlessRuleId): void {
                if ($unlessRuleId !== null && \in_array($unlessRuleId, $event->getChannelContext()->getRuleIds(), true)) {
                    return;
                }

                $event->getCriteria()->addFilter(
                    new NotFilter(NotFilter::CONNECTION_AND, [new EqualsAnyFilter('category.id', $categoryIds)])
                );
            }
        );
    }

    private function createAlwaysValidRule(): string
    {
        static::getContainer()->get('rule.repository')->create([[
            'id' => $this->ids->create('always-valid-rule'),
            'name' => 'always valid',
            'priority' => 1,
            'conditions' => [['type' => 'alwaysValid']],
        ]], Context::createDefaultContext());

        // the rule loader keeps the rules it loaded before in memory
        static::getContainer()->get(CartRuleLoader::class)->invalidate();

        return $this->ids->get('always-valid-rule');
    }

    private function createCheck(): BlogListingReadinessCheck
    {
        return $this->getContainer()->get(BlogListingReadinessCheck::class);
    }

    private function createChannels(): void
    {
        $this->connection->executeStatement('DELETE FROM `channel_domain`');
        $this->createChannel([
            'id' => $this->ids->create('sales-channel-1'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);
        $this->createChannel([
            'id' => $this->ids->create('sales-channel-2'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://shop.test',
                ],
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function createMainNavigationWithChannelAssignment(string $channelId, bool $toChild): array
    {
        $context = Context::createDefaultContext();

        $parent = $this->getCategoryData(withCmsPage: false);
        $blogs = $this->getBlogData($channelId);

        $child = $this->getCategoryData(withCmsPage: true);
        $child['parentId'] = $parent['id'];
        $child['blogs'] = $blogs;

        $this->categoryRepository->upsert([$parent, $child], $context);

        $mainId = $toChild ? $child['id'] : $parent['id'];

        $this->channelRepository->update([
            [
                'id' => $channelId,
                'navigationCategoryId' => $mainId,
                'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            ],
        ], $context);

        return [(string) $parent['id'], (string) $child['id']];
    }

    private function createMainNavigationWithoutBlogs(): void
    {
        $context = Context::createDefaultContext();

        $parent = $this->getCategoryData(withCmsPage: false);

        $this->categoryRepository->upsert([$parent], $context);

        $this->channelRepository->update([
            [
                'id' => $this->ids->get('sales-channel-1'),
                'navigationCategoryId' => $parent['id'],
                'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            ],
        ], $context);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getBlogData(string $channelId): array
    {
        $blogs = [];
        for ($i = 0; $i < 5; ++$i) {
            $blog = (new BlogBuilder($this->ids, 'blog-' . $i))
                ->name('Test-' . $i)
                ->price(10)
                ->manufacturer('manufacturer')
                ->tax('tax')
                ->visibility($channelId)
                ->build();

            unset($blog['type']);
            $blogs[] = $blog;
        }

        return $blogs;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCategoryData(bool $withCmsPage): array
    {
        $categoryId = Uuid::randomHex();
        $pageId = Uuid::randomHex();
        $streamId = Uuid::randomHex();

        $data = [
            'id' => $categoryId,
            'name' => 'Test',
            'blogAssignmentType' => CategoryDefinition::BLOG_ASSIGNMENT_TYPE_CONTENT_STREAM,
            'contentStreamId' => $streamId,
            'contentStream' => [
                'id' => $streamId,
                'name' => 'test',
                'filters' => [[
                    'type' => 'equals',
                    'field' => 'active',
                    'value' => '1',
                ]],
            ],
        ];

        if ($withCmsPage) {
            $data['cmsPageId'] = $pageId;
            $data['cmsPage'] = [
                'id' => $pageId,
                'type' => 'blog_list',
                'sections' => [
                    [
                        'position' => 0,
                        'type' => 'sidebar',
                        'blocks' => [
                            [
                                'type' => 'blog-listing',
                                'position' => 1,
                                'slots' => [
                                    ['type' => 'blog-listing', 'slot' => 'content'],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return $data;
    }
}
