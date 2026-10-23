<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Elasticsearch\Blog;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Elasticsearch\Blog\ElasticsearchOptimizeSwitch;
use Contena\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests that the custom BM25 similarity configuration (b=0 for structured fields,
 * b=0.75 for long-form text) produces correct ranking behavior.
 *
 * @internal
 */
class BM25SimilarityRankingTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use ElasticsearchTestTestBehaviour;
    use FilesystemBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use ChannelApiTestBehaviour;
    use SessionTestBehaviour;

    private static IdsCollection $ids;

    /**
     * @param list<array<string, mixed>> $blogs
     * @param list<string> $searchFields
     * @param array<string, int> $fieldScores
     * @param list<string> $expectedOrder first element = highest ranked
     * @param 'strict'|'equal_scores'|'different_scores' $scoreAssertion
     *                                                                   - strict: asserts exact ranking order
     *                                                                   - equal_scores: asserts all expected blogs have near-equal scores (proves b=0)
     *                                                                   - different_scores: asserts significant score gap between blogs (proves sw_length_norm b=0.75)
     */
    #[DataProvider('similarityRankingProvider')]
    #[TestDox('$_dataName')]
    public function testSimilarityRanking(
        array $blogs,
        string $term,
        array $searchFields,
        array $fieldScores,
        array $expectedOrder,
        string $scoreAssertion = 'strict',
    ): void {
        $this->clearElasticsearch();

        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM blog');

        static::getContainer()->get(AbstractKeyValueStorage::class)->set(ElasticsearchOptimizeSwitch::FLAG, true);

        static::getContainer()->get('blog.repository')->create($blogs, Context::createDefaultContext());

        $this->setSearchConfiguration(true, $searchFields);
        $this->setSearchScores($fieldScores);

        $this->indexElasticSearch();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm($term);

        $definition = static::getContainer()->get(BlogDefinition::class);
        $result = $this->createEntitySearcher()->search($definition, $criteria, Context::createDefaultContext());

        $resultKeys = array_map(
            static fn (string $id) => self::$ids->getKey($id),
            array_values($result->getIds())
        );

        $topResults = \array_slice($resultKeys, 0, \count($expectedOrder));

        $scores = [];
        foreach ($expectedOrder as $key) {
            $scores[$key] = $result->getScore(self::$ids->get($key));
        }

        $values = array_values($scores);
        static::assertNotCount(0, $values, 'No scores found for expected blogs');
        $max = max($values);
        $min = min($values);
        $ratio = $max > 0 ? $min / $max : 1.0;

        switch ($scoreAssertion) {
            case 'equal_scores':
                // With b=0, scores should be nearly identical.
                // Ratio > 0.95 means < 5% difference — tight enough to catch b=0.75
                // (which causes 50%+ difference) while tolerating floating-point rounding.
                static::assertGreaterThan(0.95, $ratio, \sprintf(
                    'Expected near-equal scores (ratio > 0.95) for [%s] but got ratio %.4F: %s',
                    implode(', ', $expectedOrder),
                    $ratio,
                    json_encode($scores, \JSON_THROW_ON_ERROR)
                ));
                break;

            case 'different_scores':
                // With sw_length_norm (b=0.75), short fields score significantly higher.
                // Ratio < 0.8 means > 20% difference — proves length normalization is active.
                static::assertLessThan(0.8, $ratio, \sprintf(
                    "Expected significant score gap (ratio < 0.8) for [%s] but got ratio %.4F: %s\nLength normalization (sw_length_norm) may not be applied to this field.",
                    implode(', ', $expectedOrder),
                    $ratio,
                    json_encode($scores, \JSON_THROW_ON_ERROR)
                ));
                static::assertSame(
                    $expectedOrder,
                    $topResults,
                    \sprintf(
                        "Expected ranking [%s] but got [%s].\nFull results: [%s]",
                        implode(', ', $expectedOrder),
                        implode(', ', $topResults),
                        implode(', ', $resultKeys),
                    )
                );
                break;

            default:
                static::assertSame(
                    $expectedOrder,
                    $topResults,
                    \sprintf(
                        "Expected ranking [%s] but got [%s].\nFull results: [%s]",
                        implode(', ', $expectedOrder),
                        implode(', ', $topResults),
                        implode(', ', $resultKeys),
                    )
                );
        }
    }

    /**
     * @return \Generator<string, array{blogs: list<array<string, mixed>>, term: string, searchFields: list<string>, fieldScores: array<string, int>, expectedOrder: list<string>, scoreAssertion?: 'strict'|'equal_scores'|'different_scores'}>
     */
    public static function similarityRankingProvider(): \Generator
    {
        self::$ids = $ids = new IdsCollection();

        yield 'Long descriptive name should not be penalized vs short name (b=0)' => [
            'blogs' => [
                self::blog($ids, 'sony-long', 'Sony Bravia XR 65-inch 4K Ultra HD Smart OLED TV with Dolby Vision IQ Dolby Atmos Google Assistant Built-in Hands-free Voice Control'),
                self::blog($ids, 'sony-short', 'Sony TV'),
            ],
            'term' => 'sony',
            'searchFields' => ['name'],
            'fieldScores' => ['name' => 1000],
            'expectedOrder' => ['sony-long', 'sony-short'],
            'scoreAssertion' => 'equal_scores',
        ];

        yield 'Name match outranks tag fuzzy match regardless of field length' => [
            'blogs' => [
                self::blogWithTags($ids, 'stihl-name', 'Stihl Motorsäge MS 271 Farm Boss', ['Benzin', 'Motorsäge']),
                self::blogWithTags($ids, 'stahl-tag', 'Werkzeug Komplett Set', ['Stahl']),
            ],
            'term' => 'stihl',
            'searchFields' => ['name', 'tags.name'],
            'fieldScores' => ['name' => 1000, 'tags.name' => 500],
            'expectedOrder' => ['stihl-name', 'stahl-tag'],
        ];

        yield 'Exact keyword match in long name beats prefix match in short name' => [
            'blogs' => [
                self::blog($ids, 'bosch-exact', 'Bosch Akku Schrauber Professional Edition'),
                self::blog($ids, 'bosch-prefix', 'Boschung Reiniger'),
            ],
            'term' => 'bosch',
            'searchFields' => ['name'],
            'fieldScores' => ['name' => 1000],
            'expectedOrder' => ['bosch-exact', 'bosch-prefix'],
        ];

        yield 'Multi-field convergence ranks above single-field match' => [
            'blogs' => [
                self::blogWithTags($ids, 'samsung-multi', 'Samsung Galaxy S24', ['Samsung', 'Galaxy', 'Smartphone']),
                self::blogWithTags($ids, 'samsung-single', 'Samsung Galaxy Tab A9', ['Tablet', 'Entertainment']),
            ],
            'term' => 'samsung',
            'searchFields' => ['name', 'tags.name'],
            'fieldScores' => ['name' => 500, 'tags.name' => 500],
            'expectedOrder' => ['samsung-multi', 'samsung-single'],
        ];

        // Filler blogs increase IDF for "waterproof" so the BM25-scored MatchQuery
        // (boost 0.8) outscores the ConstantScore prefix query (boost 0.4).
        // With 10 docs and only 2 containing "waterproof", IDF ≈ 1.5, making MatchQuery the
        // dis_max winner — which is where sw_length_norm (b=0.75) creates observable score differences.
        $fillerBlogs = [];
        for ($i = 1; $i <= 8; ++$i) {
            $fillerBlogs[] = self::blogWithDescription($ids, "filler-$i", "Blog $i", "Generic description without the target keyword number $i");
        }

        yield 'Description field uses length normalization (sw_length_norm b=0.75)' => [
            'blogs' => array_merge([
                self::blogWithDescription($ids, 'waterproof-focused', 'Outdoor Gear A', 'Waterproof jacket with sealed seams'),
                self::blogWithDescription($ids, 'waterproof-buried', 'Outdoor Gear B', 'This premium outdoor jacket features a scratch-resistant coating with anti-reflective layer a titanium zipper GPS pocket heart rate strap barometer altimeter compass and is waterproof to withstand the harshest weather conditions making it the perfect companion for any outdoor expedition across mountains deserts and tundra'),
            ], $fillerBlogs),
            'term' => 'waterproof',
            'searchFields' => ['description'],
            'fieldScores' => ['description' => 1000],
            'expectedOrder' => ['waterproof-focused', 'waterproof-buried'],
            'scoreAssertion' => 'different_scores',
        ];
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    /**
     * @return array<string, mixed>
     */
    private static function blog(IdsCollection $ids, string $key, string $name): array
    {
        return (new BlogBuilder($ids, $key))
            ->name($name)
            ->price(100)
            ->visibility()
            ->build();
    }

    /**
     * @param list<string> $tags
     *
     * @return array<string, mixed>
     */
    private static function blogWithTags(IdsCollection $ids, string $key, string $name, array $tags): array
    {
        $builder = (new BlogBuilder($ids, $key))
            ->name($name)
            ->price(100)
            ->visibility();

        foreach ($tags as $tag) {
            $builder->tag($tag);
        }

        return $builder->build();
    }

    /**
     * @return array<string, mixed>
     */
    private static function blogWithDescription(IdsCollection $ids, string $key, string $name, string $description): array
    {
        return (new BlogBuilder($ids, $key))
            ->name($name)
            ->description($description)
            ->price(100)
            ->visibility()
            ->build();
    }
}
