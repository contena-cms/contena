<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\SystemCheck\BlogListingReadinessCheck;

/**
 * @internal
 */
class BlogListingReadinessCheckTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    /**
     * @var EntityRepository<CategoryContentLayoutCollection>
     */
    private EntityRepository $categoryContentLayoutRepository;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    private string $contentLayoutId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->categoryContentLayoutRepository = static::getContainer()->get('category_content_layout.repository');
        $this->channelRepository = static::getContainer()->get('channel.repository');
        $this->contentLayoutId = $this->findCategoryContentLayoutId();
        $this->createChannels();
    }

    public function testCheckBlogListing(): void
    {
        $this->createMainNavigationWithChannelAssignment($this->ids->get('channel-1'), true);
        $this->createMainNavigationWithChannelAssignment($this->ids->get('channel-2'), false);

        $result = $this->createCheck()->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
    }

    public function testCheckBlogListingWithoutBlogs(): void
    {
        $this->createMainNavigationWithChannelAssignment($this->ids->get('channel-1'), false);

        $result = $this->createCheck()->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
    }

    private function createCheck(): BlogListingReadinessCheck
    {
        return static::getContainer()->get(BlogListingReadinessCheck::class);
    }

    private function createChannels(): void
    {
        $this->connection->executeStatement('DELETE FROM `channel_domain`');
        foreach (['channel-1' => 'http://example.com', 'channel-2' => 'http://shop.test'] as $key => $url) {
            $this->createChannel([
                'id' => $this->ids->create($key),
                'domains' => [[
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => $url,
                ]],
            ]);
        }
    }

    private function createMainNavigationWithChannelAssignment(string $channelId, bool $assignChild): void
    {
        $context = Context::createDefaultContext();
        $rootId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $this->categoryRepository->create([
            ['id' => $rootId, 'name' => 'Root', 'active' => true],
            ['id' => $childId, 'parentId' => $rootId, 'name' => 'Articles', 'active' => true],
        ], $context);

        $assignedCategoryId = $assignChild ? $childId : $rootId;
        $this->categoryContentLayoutRepository->create([[
            'id' => Uuid::randomHex(),
            'categoryId' => $assignedCategoryId,
            'channelId' => $channelId,
            'contentLayoutId' => $this->contentLayoutId,
        ]], $context);

        $this->channelRepository->update([[
            'id' => $channelId,
            'navigationCategoryId' => $rootId,
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
        ]], $context);
    }

    private function findCategoryContentLayoutId(): string
    {
        $criteria = new Criteria()->addFilter(new EqualsFilter('rootSource', 'category'))->setLimit(1);
        $id = static::getContainer()->get('content_layout.repository')->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }
}
