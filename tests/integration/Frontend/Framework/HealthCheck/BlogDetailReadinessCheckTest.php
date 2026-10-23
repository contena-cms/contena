<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\CartRuleLoader;
use Contena\Core\Content\Blog\BlogCollection;
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
use Contena\Core\System\Channel\Event\ChannelProcessCriteriaEvent;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\SystemCheck\BlogDetailReadinessCheck;

/**
 * @internal
 */
class BlogDetailReadinessCheckTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use ChannelApiTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->ids = new IdsCollection();

        $this->createChannels();
    }

    public function testAllChecksAreHealthy(): void
    {
        $this->createBlogs();

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
    }

    public function testCheckWithoutBlogs(): void
    {
        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::SKIPPED, $result->status);
    }

    public function testCheckIsHealthyWhenAllBlogsAreRestrictedByAnExtension(): void
    {
        $blogIds = $this->getBlogIds($this->createBlogs());

        // simulates an extension that restricts blog visibility by rules, e.g. Dynamic Access:
        // for an anonymous visitor the rule does not match, so the blogs are filtered out
        $this->restrictBlogs($blogIds);

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::SKIPPED, $result->status);
    }

    public function testCheckPicksABlogThatIsNotRestrictedByAnExtension(): void
    {
        $blogIds = $this->getBlogIds($this->createBlogs());
        $additionalBlogIds = $this->getBlogIds($this->createBlogs('additional-blog-'));

        // restrict the blog that would be selected first, the check has to fall back to the other one
        $restrictedIds = [];
        foreach ($blogIds as $index => $blogId) {
            $ids = [$blogId, $additionalBlogIds[$index]];
            sort($ids);
            $restrictedIds[] = $ids[0];
        }

        $this->restrictBlogs($restrictedIds);

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
        static::assertCount(2, $result->extra);
    }

    /**
     * Extensions like Dynamic Access can also expose a blog only for a rule that matches an anonymous
     * visitor. The probe request detects those rules, so the lookup has to as well, otherwise the check
     * reports SKIPPED while the blog detail pages render fine.
     */
    public function testBlogsRestrictedToARuleMatchingAnAnonymousVisitorAreProbed(): void
    {
        $blogIds = $this->getBlogIds($this->createBlogs());
        $this->restrictBlogs($blogIds, unlessRuleId: $this->createAlwaysValidRule());

        $result = $this->createCheck()->run();

        static::assertSame(Status::OK, $result->status);
        static::assertCount(2, $result->extra);
    }

    /**
     * `BlogDetailRoute::addCloseoutFilter()` only excludes closeout blogs out of stock while
     * `core.listing.hideCloseoutBlogsWhenOutOfStock` is on. With the setting off their detail pages
     * render, so the check must still probe them instead of reporting SKIPPED.
     */
    public function testClosedOutBlogsAreProbedWhenTheSettingIsOff(): void
    {
        $this->createClosedOutBlogs();
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', false);

        $result = $this->createCheck()->run();

        static::assertSame(Status::OK, $result->status);
        static::assertCount(2, $result->extra);
    }

    public function testClosedOutBlogsAreSkippedWhenTheSettingIsOn(): void
    {
        $this->createClosedOutBlogs();
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', true);

        $result = $this->createCheck()->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::SKIPPED, $result->status);
    }

    /**
     * The only blogs in either sales channel are closeout and out of stock, so the setting alone
     * decides whether the check has anything to probe.
     */
    private function createClosedOutBlogs(): void
    {
        $blogs = [];
        foreach ([$this->ids->get('sales-channel-1'), $this->ids->get('sales-channel-2')] as $index => $id) {
            $blogs[] = (new BlogBuilder($this->ids, 'closeout-' . $index))
                ->name('Closeout-' . $index)
                ->price(10)
                ->manufacturer('manufacturer')
                ->tax('tax')
                ->visibility($id)
                ->stock(0)
                ->closeout()
                ->build();
        }

        $this->blogRepository->create($blogs, Context::createDefaultContext());
    }

    /**
     * @param list<string> $blogIds
     */
    private function restrictBlogs(array $blogIds, ?string $unlessRuleId = null): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            'channel.blog.process.criteria',
            static function (ChannelProcessCriteriaEvent $event) use ($blogIds, $unlessRuleId): void {
                if ($unlessRuleId !== null && \in_array($unlessRuleId, $event->getChannelContext()->getRuleIds(), true)) {
                    return;
                }

                $event->getCriteria()->addFilter(
                    new NotFilter(NotFilter::CONNECTION_AND, [new EqualsAnyFilter('blog.id', $blogIds)])
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

    /**
     * @param list<array<string, mixed>> $blogs
     *
     * @return list<string>
     */
    private function getBlogIds(array $blogs): array
    {
        return array_map(static fn (array $blog) => (string) $blog['id'], $blogs);
    }

    private function createCheck(): BlogDetailReadinessCheck
    {
        return $this->getContainer()->get(BlogDetailReadinessCheck::class);
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
     * @return list<array<string, mixed>>
     */
    private function createBlogs(string $keyPrefix = 'blog-'): array
    {
        $channelIds = [
            $this->ids->get('sales-channel-1'),
            $this->ids->get('sales-channel-2'),
        ];

        $blogs = [];
        foreach ($channelIds as $index => $id) {
            $blogs[] = (new BlogBuilder($this->ids, $keyPrefix . $index))
                ->name('Test-' . $keyPrefix . $index)
                ->price(10)
                ->manufacturer('manufacturer')
                ->tax('tax')
                ->visibility($id)
                ->build();
        }

        $this->blogRepository->create($blogs, Context::createDefaultContext());

        return $blogs;
    }
}
