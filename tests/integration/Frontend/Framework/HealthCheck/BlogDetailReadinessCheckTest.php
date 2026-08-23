<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\SystemCheck\BlogDetailReadinessCheck;

/**
 * @internal
 */
class BlogDetailReadinessCheckTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

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

        $result = $this->createCheck()->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
    }

    public function testCheckWithoutBlogs(): void
    {
        $result = $this->createCheck()->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::SKIPPED, $result->status);
    }

    private function createCheck(): BlogDetailReadinessCheck
    {
        return static::getContainer()->get(BlogDetailReadinessCheck::class);
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

    private function createBlogs(): void
    {
        $blogs = [];
        $assignments = [];
        $contentLayoutId = $this->findContentLayoutId('blog');
        foreach (['channel-1', 'channel-2'] as $index => $channelKey) {
            $blog = new BlogBuilder($this->ids, 'blog-' . $index)
                ->name('Test-' . $index)
                ->visibility($this->ids->get($channelKey))
                ->build();
            $assignments[] = [
                'id' => Uuid::randomHex(),
                'blogId' => $blog['id'],
                'channelId' => $this->ids->get($channelKey),
                'contentLayoutId' => $contentLayoutId,
            ];
            $blogs[] = $blog;
        }

        $this->blogRepository->create($blogs, Context::createDefaultContext());
        static::getContainer()->get('blog_content_layout.repository')->create($assignments, Context::createDefaultContext());
    }

    private function findContentLayoutId(string $rootSource): string
    {
        $criteria = new Criteria()->addFilter(new EqualsFilter('rootSource', $rootSource))->setLimit(1);
        $id = static::getContainer()->get('content_layout.repository')->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }
}
