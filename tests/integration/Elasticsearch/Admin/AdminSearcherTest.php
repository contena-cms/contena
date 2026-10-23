<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Elasticsearch\Admin\AdminSearcher;
use Contena\Elasticsearch\Profiler\ClientProfiler;
use Contena\Elasticsearch\Test\AdminElasticsearchTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class AdminSearcherTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AdminElasticsearchTestBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private AdminSearcher $searcher;

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->searcher = static::getContainer()->get(AdminSearcher::class);

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM blog');

        $this->clearElasticsearch();
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM blog');

        $this->clearElasticsearch();
    }

    public function testNonNumericSearchStillWorks(): void
    {
        $ids = new IdsCollection();
        $blogLaptopId = $ids->get('TEST-LAPTOP');

        $blogs = [
            (new BlogBuilder($ids, 'TEST-LAPTOP', 10))
                ->name('Laptop Computer')
                ->price(100)
                ->build(),
        ];

        $this->blogRepository->create($blogs, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['blog']]);
        $this->refreshIndex();

        $results = $this->searcher->search('laptop', ['blog'], Context::createDefaultContext());

        static::assertNotCount(0, $results);
        static::assertArrayHasKey('blog', $results);
        static::assertGreaterThan(0, $results['blog']['total']);

        static::assertInstanceOf(BlogCollection::class, $results['blog']['data']);
        $foundBlogIds = $results['blog']['data']->getIds();
        static::assertContains($blogLaptopId, $foundBlogIds, 'Laptop should be found when searching for "laptop"');

        $prefixResults = $this->searcher->search('LAPTO', ['blog'], Context::createDefaultContext());

        static::assertNotCount(0, $prefixResults, 'Case-insensitive blog-name prefix search should find "Laptop Computer".');
        static::assertArrayHasKey('blog', $prefixResults);
        static::assertInstanceOf(BlogCollection::class, $prefixResults['blog']['data']);
        static::assertContains($blogLaptopId, $prefixResults['blog']['data']->getIds(), 'Laptop should be found when searching for the uppercase prefix "LAPTO"');
    }

    public function testNumericSearchFindsSubstringMatches(): void
    {
        $ids = new IdsCollection();
        $blogX3800Id = $ids->get('TEST-X3800');
        $blogABC3800XYZId = $ids->get('TEST-ABC3800XYZ');
        $blog3800Id = $ids->get('TEST-3800');

        $blogs = [
            (new BlogBuilder($ids, 'TEST-X3800', 10))
                ->name('Blog X38000')
                ->price(100)
                ->build(),
            (new BlogBuilder($ids, 'TEST-ABC3800XYZ', 10))
                ->name('Blog ABC38000XYZ')
                ->price(100)
                ->build(),
            (new BlogBuilder($ids, 'TEST-3800', 10))
                ->name('Blog 38000')
                ->price(100)
                ->build(),
        ];

        $this->blogRepository->create($blogs, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['blog']]);
        $this->refreshIndex();

        $results = $this->searcher->search('38000', ['blog'], Context::createDefaultContext());

        static::assertNotCount(0, $results);
        static::assertArrayHasKey('blog', $results);
        static::assertGreaterThanOrEqual(3, $results['blog']['total'], 'Should find at least 3 blogs containing "3800"');

        static::assertInstanceOf(BlogCollection::class, $results['blog']['data']);
        $foundBlogIds = $results['blog']['data']->getIds();

        static::assertContains($blogX3800Id, $foundBlogIds, 'Blog X3800 should be found');
        static::assertContains($blogABC3800XYZId, $foundBlogIds, 'Blog ABC3800XYZ should be found');
        static::assertContains($blog3800Id, $foundBlogIds, 'Blog 3800 should be found');
    }

    public function testNumericSearchDoesNotMatchSimilarNumbers(): void
    {
        $ids = new IdsCollection();
        $blog3800Id = $ids->get('TEST-3800');
        $blog3000Id = $ids->get('TEST-3000');
        $blog3801Id = $ids->get('TEST-3801');

        $blogs = [
            (new BlogBuilder($ids, 'TEST-3800', 10))
                ->name('Blog 3800')
                ->price(100)
                ->build(),
            (new BlogBuilder($ids, 'TEST-3000', 10))
                ->name('Blog 3000')
                ->price(100)
                ->build(),
            (new BlogBuilder($ids, 'TEST-3801', 10))
                ->name('Blog 3801')
                ->price(100)
                ->build(),
        ];

        $this->blogRepository->create($blogs, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['blog']]);
        $this->refreshIndex();

        $results = $this->searcher->search('3800', ['blog'], Context::createDefaultContext());

        static::assertNotCount(0, $results);
        static::assertArrayHasKey('blog', $results);

        static::assertInstanceOf(BlogCollection::class, $results['blog']['data']);
        $foundBlogIds = $results['blog']['data']->getIds();

        static::assertContains($blog3800Id, $foundBlogIds, 'Blog 3800 should be found');

        static::assertNotContains($blog3000Id, $foundBlogIds, 'Blog 3000 should NOT be found (no fuzziness)');

        static::assertNotContains($blog3801Id, $foundBlogIds, 'Blog 3801 should NOT be found (different number)');
    }

    public function testShortNumericPrefixFindsBlogName(): void
    {
        $ids = new IdsCollection();
        $blogId = $ids->get('RUNNING-CLUB');
        $eanOwnerId = $ids->get('EAN-OWNER');

        $eanOwner = (new BlogBuilder($ids, 'EAN-OWNER', 10))
            ->name('Genuine Item')
            ->price(100)
            ->build();
        $eanOwner['ean'] = '4572324423421';

        $blogs = [
            (new BlogBuilder($ids, 'RUNNING-CLUB', 10))
                ->name('running club 4572324423420')
                ->price(100)
                ->build(),
            $eanOwner,
            (new BlogBuilder($ids, 'OTHER', 10))
                ->name('Wireless Headphones')
                ->price(100)
                ->build(),
        ];

        $this->blogRepository->create($blogs, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['blog']]);
        $this->refreshIndex();

        $profiler = $this->startRecordingAdminSearchRequests();

        $results = $this->searcher->search('457', ['blog'], Context::createDefaultContext());

        // Control arm: the relevance order OpenSearch itself returned for the query the searcher sent.
        $hits = $this->recordedBlogHits($profiler);

        static::assertNotCount(0, $hits, 'Raw OpenSearch search must return hits for a short numeric prefix in the blog name.');
        static::assertSame(
            $blogId,
            $hits[0]['id'],
            \sprintf(
                'Expected the blog with "457" in its name to rank before the blog with only an EAN prefix match. Raw hit order: %s',
                json_encode(array_column($hits, 'id'), \JSON_THROW_ON_ERROR)
            )
        );

        static::assertNotCount(0, $results, 'Search must return hits for a short numeric prefix in the blog name.');
        static::assertArrayHasKey('blog', $results);
        static::assertInstanceOf(BlogCollection::class, $results['blog']['data']);

        $foundBlogIds = array_values($results['blog']['data']->getIds());
        static::assertSame(
            $blogId,
            $foundBlogIds[0] ?? null,
            \sprintf('Blog name prefix match should rank before EAN prefix match. Hit order: %s', json_encode($foundBlogIds, \JSON_THROW_ON_ERROR))
        );
        static::assertContains(
            $eanOwnerId,
            $foundBlogIds,
            'Blog with EAN prefix "457" should still be found, but not ranked before the name match.'
        );
    }

    /**
     * Regression guard for #15828: a full GTIN-13 EAN search must rank the
     * owning blog first, even with an adversarial decoy whose name shares
     * trigrams with the EAN. The "Cable 4572324423420" decoy reproduces the
     * original failure shape.
     */
    public function testExactEanSearchRanksOwnerAboveTrigramOverlap(): void
    {
        $ids = new IdsCollection();
        $ean = '4572324423421';
        $ownerId = $ids->get('OWNER');

        $owner = (new BlogBuilder($ids, 'OWNER', 10))
            ->name('Genuine Item')
            ->price(100)
            ->build();
        $owner['ean'] = $ean;

        $blogs = [
            $owner,
            // Adversarial: name contains a digit string sharing nearly every
            // trigram with the searched EAN. The fix must isolate the owner
            // from this overlap.
            (new BlogBuilder($ids, 'DECOY-CABLE', 10))
                ->name('Cable 4572324423420')
                ->price(100)
                ->build(),
            (new BlogBuilder($ids, 'DECOY-1', 10))
                ->name('Wireless Headphones')
                ->price(100)
                ->build(),
            (new BlogBuilder($ids, 'DECOY-2', 10))
                ->name('Garden Hose 25ft')
                ->price(100)
                ->build(),
            (new BlogBuilder($ids, 'DECOY-3', 10))
                ->name('Office Chair Ergonomic')
                ->price(100)
                ->build(),
        ];

        $this->blogRepository->create($blogs, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['blog']]);
        $this->refreshIndex();

        $profiler = $this->startRecordingAdminSearchRequests();

        $results = $this->searcher->search($ean, ['blog'], Context::createDefaultContext());

        // Control arm: the relevance order OpenSearch itself returned for the query the searcher sent.
        $hits = $this->recordedBlogHits($profiler);

        static::assertNotCount(0, $hits, 'Raw OpenSearch search must return hits for the exact EAN.');
        $topHit = $hits[0];
        static::assertSame(
            $ownerId,
            $topHit['id'],
            \sprintf(
                'Expected the EAN-owning blog to have the highest raw OpenSearch score, got "%s". Raw hit order: %s',
                $topHit['id'],
                json_encode(array_column($hits, 'id'), \JSON_THROW_ON_ERROR)
            )
        );

        static::assertNotCount(0, $results, 'Search must return hits for the exact EAN.');
        static::assertArrayHasKey('blog', $results);
        static::assertInstanceOf(BlogCollection::class, $results['blog']['data']);

        $foundBlogIds = array_values($results['blog']['data']->getIds());
        static::assertSame(
            $ownerId,
            $foundBlogIds[0] ?? null,
            \sprintf(
                'Expected the EAN-owning blog to rank first, got "%s". Hit order: %s',
                $foundBlogIds[0] ?? 'null',
                json_encode($foundBlogIds, \JSON_THROW_ON_ERROR)
            )
        );
    }

    /**
     * Whole-word autocomplete: "shirt" must find "T-Shirt" via the
     * word-delimiter token split on the `completion` main field, even though
     * the 5-char query is longer than the ngram subfield's max_gram.
     */
    public function testWholeWordAutocompleteFindsHyphenatedNames(): void
    {
        $ids = new IdsCollection();
        $shirtId = $ids->get('SHIRT');

        $blogs = [
            (new BlogBuilder($ids, 'SHIRT', 10))
                ->name('T-Shirt')
                ->price(100)
                ->build(),
            (new BlogBuilder($ids, 'PANTS', 10))
                ->name('Pants')
                ->price(100)
                ->build(),
        ];

        $this->blogRepository->create($blogs, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['blog']]);
        $this->refreshIndex();

        $results = $this->searcher->search('shirt', ['blog'], Context::createDefaultContext());

        static::assertNotCount(0, $results, '"shirt" should find blogs whose names contain the word — including hyphenated forms like "T-Shirt".');
        static::assertArrayHasKey('blog', $results);
        static::assertInstanceOf(BlogCollection::class, $results['blog']['data']);

        $foundIds = array_values($results['blog']['data']->getIds());
        static::assertContains(
            $shirtId,
            $foundIds,
            \sprintf('"T-Shirt" must be found by the whole-word "shirt" query. Hit order: %s', json_encode($foundIds, \JSON_THROW_ON_ERROR))
        );
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    /**
     * Starts recording the requests the searcher sends, so a test can compare the relevance order
     * OpenSearch returned against the order the public search() hands back. In debug mode, which the
     * test suite runs in, the admin client is a ClientProfiler that captures every request with its
     * verbatim params and raw response, so nothing has to be rebuilt or reflected into.
     */
    private function startRecordingAdminSearchRequests(): ClientProfiler
    {
        $client = static::getContainer()->get('admin.openSearch.client');

        if (!$client instanceof ClientProfiler) {
            static::markTestSkipped('The admin OpenSearch client only records requests when kernel.debug is enabled.');
        }

        // drop the indexing and refresh traffic, so only the search under test remains
        $client->resetRequests();

        return $client;
    }

    /**
     * Reads the blog hits out of the recorded msearch, in the order OpenSearch scored them.
     *
     * @return list<array{id: string, score: float}>
     */
    private function recordedBlogHits(ClientProfiler $profiler): array
    {
        $requests = $profiler->getCalledRequests();
        static::assertCount(1, $requests, 'The searcher is expected to issue exactly one msearch per search().');

        $responses = $requests[0]['response']['responses'] ?? null;
        static::assertIsArray($responses, 'The recorded msearch response must contain a responses list.');
        static::assertNotCount(0, $responses, 'The recorded msearch response must contain at least one sub-response.');

        $hits = $responses[0]['hits']['hits'] ?? [];
        static::assertIsArray($hits);

        // `_id` is the field the searcher itself reads (see AdminSearcher::parseResponse()), so the
        // control arm and the production path agree on what identifies a hit.
        return array_values(array_map(static function (array $hit): array {
            static::assertIsString($hit['_id'] ?? null);

            return [
                'id' => $hit['_id'],
                'score' => (float) $hit['_score'],
            ];
        }, $hits));
    }
}
