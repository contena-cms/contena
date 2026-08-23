<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Elasticsearch\Blog;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests that tie_breaker in dis_max queries produces correct ranking by rewarding
 * documents with broader matching evidence (multiple clause types, multiple languages).
 *
 * @internal
 */
class TieBreakerRankingTest extends TestCase
{
    use CacheTestBehaviour;
    use ChannelApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use ElasticsearchTestTestBehaviour;
    use FilesystemBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use SessionTestBehaviour;

    private static IdsCollection $ids;

    /**
     * @param list<array<string, mixed>> $blogs
     * @param list<string> $searchFields
     * @param array<string, int> $fieldScores
     * @param list<string> $expectedOrder first element = highest ranked
     */
    #[DataProvider('tieBreakerRankingProvider')]
    #[TestDox('$_dataName')]
    public function testTieBreakerRanking(
        array $blogs,
        string $term,
        array $searchFields,
        array $fieldScores,
        array $expectedOrder,
    ): void {
        $this->clearElasticsearch();

        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM blog');

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
            static fn (string $id): ?string => self::$ids->getKey($id),
            array_values($result->getIds())
        );

        $topResults = \array_slice($resultKeys, 0, \count($expectedOrder));

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

    /**
     * @return \Generator<string, array{blogs: list<array<string, mixed>>, term: string, searchFields: list<string>, fieldScores: array<string, int>, expectedOrder: list<string>}>
     */
    public static function tieBreakerRankingProvider(): \Generator
    {
        self::$ids = $ids = new IdsCollection();

        yield 'Exact match in long name ranks above fuzzy match in short name (field length normalization)' => [
            'blogs' => [
                self::blog($ids, 'stihl-blog', 'Stihl Motorsäge MS 271 Farm Boss'),
                self::blog($ids, 'stahl-blog', 'Stahl Bohrer'),
            ],
            'term' => 'stihl',
            'searchFields' => ['name'],
            'fieldScores' => ['name' => 1000],
            'expectedOrder' => ['stihl-blog', 'stahl-blog'],
        ];

        yield 'Name exact match ranks above tag-only fuzzy match' => [
            'blogs' => [
                self::blogWithTags($ids, 'stihl-name-match', 'Stihl Motorsäge', ['Benzin', 'Motorsäge']),
                self::blogWithTags($ids, 'stahl-tag-match', 'Werkzeug Komplett Set', ['Stahl']),
            ],
            'term' => 'stihl',
            'searchFields' => ['name', 'tags.name'],
            'fieldScores' => ['name' => 1000, 'tags.name' => 500],
            'expectedOrder' => ['stihl-name-match', 'stahl-tag-match'],
        ];

        yield 'Name + tag convergence ranks above name-only match' => [
            'blogs' => [
                self::blogWithTags($ids, 'stihl-both', 'Stihl Motorsäge MS 271', ['Stihl', 'Motorsäge']),
                self::blogWithTags($ids, 'stihl-name-only', 'Stihl Kettensäge Professional Edition', ['Benzin', 'Profi']),
            ],
            'term' => 'stihl',
            'searchFields' => ['name', 'tags.name'],
            'fieldScores' => ['name' => 500, 'tags.name' => 500],
            'expectedOrder' => ['stihl-both', 'stihl-name-only'],
        ];

        yield 'Phrase match ranks above scattered token match' => [
            'blogs' => [
                self::blog($ids, 'iphone-phrase', 'iPhone Case Transparent'),
                self::blog($ids, 'iphone-scattered', 'iPhone Ladegerät Silikon Case'),
            ],
            'term' => 'iphone case',
            'searchFields' => ['name'],
            'fieldScores' => ['name' => 1000],
            'expectedOrder' => ['iphone-phrase', 'iphone-scattered'],
        ];

        yield 'Exact keyword match ranks above prefix-only match' => [
            'blogs' => [
                self::blog($ids, 'bosch-exact', 'Bosch Akku Schrauber'),
                self::blog($ids, 'bosch-prefix', 'Boschung Premium Reiniger'),
            ],
            'term' => 'bosch',
            'searchFields' => ['name'],
            'fieldScores' => ['name' => 1000],
            'expectedOrder' => ['bosch-exact', 'bosch-prefix'],
        ];

        yield 'Multi-field match outranks single-field match with same term' => [
            'blogs' => [
                self::blogWithTags(
                    $ids,
                    'samsung-multi',
                    'Samsung Galaxy S24',
                    ['Samsung', 'Galaxy', 'Smartphone'],
                ),
                self::blogWithTags(
                    $ids,
                    'samsung-single',
                    'Samsung Galaxy Tab A9',
                    ['Tablet', 'Entertainment'],
                ),
            ],
            'term' => 'samsung',
            'searchFields' => ['name', 'tags.name'],
            'fieldScores' => ['name' => 500, 'tags.name' => 500],
            'expectedOrder' => ['samsung-multi', 'samsung-single'],
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
        return new BlogBuilder($ids, $key)
            ->name($name)
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
        $builder = new BlogBuilder($ids, $key)
            ->name($name)
            ->visibility();

        foreach ($tags as $tag) {
            $builder->tag($tag);
        }

        return $builder->build();
    }
}
