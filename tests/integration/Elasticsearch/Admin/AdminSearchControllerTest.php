<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Elasticsearch\Test\AdminElasticsearchTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class AdminSearchControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AdminElasticsearchTestBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * Built once for the whole class by the first run of setUp(). The first-test-indexes pattern was
     * replaced by guarded setUp because a data-provided test (testElasticSearch) can no longer also
     * receive the ids via #[Depends] - see NoDependsWithDataProviderRule.
     */
    private static IdsCollection $indexedIds;

    protected function setUp(): void
    {
        if (!static::getContainer()->getParameter('elasticsearch.administration.enabled')) {
            static::markTestSkipped('No OPENSEARCH configured');
        }

        $this->connection = static::getContainer()->get(Connection::class);

        $this->blogRepository = static::getContainer()->get('blog.repository');

        if (!isset(self::$indexedIds)) {
            self::$indexedIds = $this->buildIndex();
        }
    }

    /**
     * @param array{term: string, entities: list<string>} $data
     * @param list<string> $expectedBlogs
     */
    #[DataProvider('providerSearchCases')]
    public function testElasticSearch(array $data, array $expectedBlogs): void
    {
        $ids = self::$indexedIds;

        $this->getBrowser()->request('POST', '/api/_admin/es-search', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR) ?: null);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content, print_r($content, true));
        static::assertNotEmpty($content['data']);
        static::assertNotEmpty($content['data']['blog']);

        $content = $content['data']['blog'];

        static::assertSame(\count($expectedBlogs), $content['total']);

        foreach ($expectedBlogs as $expectedBlog) {
            $id = $ids->get($expectedBlog);
            static::assertNotEmpty($content['data'][$id]);
            static::assertSame($id, $content['data'][$id]['id']);
        }
    }

    /**
     * @return \Generator<string, array{array{term: string, entities: list<string>}, list<string>}>
     */
    public static function providerSearchCases(): \Generator
    {
        yield 'search with normal term' => [
            [
                'term' => 'laptop gold',
                'entities' => ['blog'],
            ],
            ['blog-1', 'blog-2', 'blog-3'],
        ];
        yield 'search a phrase' => [
            [
                'term' => '"gold laptop"',
                'entities' => ['blog'],
            ],
            ['blog-1'],
        ];
        yield 'search with AND' => [
            [
                'term' => 'laptop AND gold',
                'entities' => ['blog'],
            ],
            ['blog-1'],
        ];
        yield 'search with OR' => [
            [
                'term' => 'laptop OR gold',
                'entities' => ['blog'],
            ],
            ['blog-1', 'blog-2', 'blog-3'],
        ];
        yield 'search with AND syntax' => [
            [
                'term' => '+laptop +gold',
                'entities' => ['blog'],
            ],
            ['blog-1'],
        ];
        yield 'search with OR syntax' => [
            [
                'term' => 'laptop | gold',
                'entities' => ['blog'],
            ],
            ['blog-1', 'blog-2', 'blog-3'],
        ];
        yield 'search with NEGATE syntax' => [
            [
                'term' => 'laptop +-gold',
                'entities' => ['blog'],
            ],
            ['blog-2'],
        ];
        yield 'search with Umlauts' => [
            [
                'term' => 'Ausländer',
                'entities' => ['blog'],
            ],
            ['blog-5'],
        ];
        yield 'search by number #1 with concatenated index' => [
            [
                'term' => '12345',
                'entities' => ['blog'],
            ],
            ['blog-6'],
        ];
        yield 'search by number #2 with concatenated index' => [
            [
                'term' => '56789',
                'entities' => ['blog'],
            ],
            ['blog-6'],
        ];
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    private function buildIndex(): IdsCollection
    {
        $this->connection->executeStatement('DELETE FROM blog');

        $this->clearElasticsearch();
        $this->indexElasticSearch(['--only' => ['blog']]);

        $ids = new IdsCollection();
        $this->createData($ids);

        $this->refreshIndex();

        return $ids;
    }

    private function createData(IdsCollection $ids): void
    {
        $blogs = [
            [
                'id' => $ids->get('blog-1'),
                'name' => 'gold laptop',
                'active' => true,
            ],
            [
                'id' => $ids->get('blog-2'),
                'name' => 'silver laptop',
                'active' => true,
            ],
            [
                'id' => $ids->get('blog-3'),
                'name' => 'gold pc',
                'active' => true,
            ],
            [
                'id' => $ids->get('blog-4'),
                'name' => 'silver pc',
                'active' => true,
            ],
            [
                'id' => $ids->get('blog-5'),
                'name' => 'Ausländer',
                'active' => true,
            ],
            [
                'id' => $ids->get('blog-6'),
                'name' => [
                    'de-DE' => '12345',
                    'en-GB' => '56789',
                ],
                'active' => true,
            ],
        ];

        $this->blogRepository->create($blogs, Context::createDefaultContext());
    }
}
