<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Elasticsearch\Blog;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
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
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class SearchCasesTest extends TestCase
{
    use CacheTestBehaviour;
    use ChannelApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use ElasticsearchTestTestBehaviour;
    use FilesystemBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use SessionTestBehaviour;

    public function testExactNameTokenMatchRanksAheadOfPrefixOnlyMatch(): void
    {
        $this->clearElasticsearch();

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM blog');

        $ids = new IdsCollection();

        static::getContainer()->get('blog.repository')->create([
            self::blog($ids, 'exact', 'Leather Jacket'),
            self::blog($ids, 'prefix', 'Leathery Jacket'),
        ], Context::createDefaultContext());

        $this->setSearchConfiguration(true, ['name']);
        $this->setSearchScores(['name' => 700]);

        $this->indexElasticSearch();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('Leather');

        $definition = static::getContainer()->get(BlogDefinition::class);
        $result = $this->createEntitySearcher()->search($definition, $criteria, Context::createDefaultContext());

        $firstId = $result->firstId();
        static::assertNotNull($firstId, print_r($result->getData(), true));
        static::assertSame('exact', $ids->getKey($firstId), print_r($result->getData(), true));
    }

    /**
     * @param array<int, array<string, mixed>> $blogs
     * @param list<string> $searchFields
     * @param array<string, int> $searchScores
     * @param list<string> $mustNotContainKeys
     */
    #[DataProvider('searchScenariosProvider')]
    public function testSearchScenarios(
        IdsCollection $ids,
        array $blogs,
        array $searchFields,
        array $searchScores,
        ?float $minScore,
        string $term,
        ?string $expectedFirst,
        array $mustNotContainKeys = [],
    ): void {
        $this->clearElasticsearch();
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM blog');
        static::getContainer()->get('blog.repository')->create($blogs, Context::createDefaultContext());

        $this->setSearchConfiguration(true, $searchFields);
        $this->setSearchScores($searchScores);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        if ($minScore !== null) {
            $systemConfig->set('core.search.minScore', $minScore);
        } else {
            $systemConfig->delete('core.search.minScore');
        }

        $this->indexElasticSearch();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm($term);

        $definition = static::getContainer()->get(BlogDefinition::class);
        $result = $this->createEntitySearcher()->search($definition, $criteria, Context::createDefaultContext());

        $hitKeys = [];
        $scores = [];
        foreach ($result->getData() as $item) {
            $key = $ids->getKey((string) $item['id']);
            if ($key === null) {
                continue;
            }
            $hitKeys[] = $key;
            $scores[$key] = $item['_score'];
        }

        if ($expectedFirst !== null) {
            static::assertNotNull($result->firstId(), 'Expected a top hit but got none. Scores: ' . print_r($scores, true));
            static::assertSame(
                $expectedFirst,
                $ids->getKey($result->firstId()),
                \sprintf('Expected "%s" to rank first. Actual ranking: %s', $expectedFirst, print_r($scores, true)),
            );
        }

        foreach ($mustNotContainKeys as $blockedKey) {
            static::assertNotContains(
                $blockedKey,
                $hitKeys,
                \sprintf('Blog "%s" should not appear in the hit list but did. Scores: %s', $blockedKey, print_r($scores, true)),
            );
        }
    }

    public function testMinScoreCutoffDropsWeakFuzzyHit(): void
    {
        $ids = new IdsCollection();
        $blogs = [
            self::blog($ids, 'strong', 'Heckenschere Professional'),
            self::blog($ids, 'weak', 'Heckeschere Weak Variant'),
        ];

        $this->clearElasticsearch();
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM blog');
        static::getContainer()->get('blog.repository')->create($blogs, Context::createDefaultContext());

        $this->setSearchConfiguration(true, ['name']);
        $this->setSearchScores(['name' => 1000]);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->delete('core.search.minScore');

        $this->indexElasticSearch();

        $scores = $this->scoresByKey($ids, 'Heckenschere');

        static::assertArrayHasKey('strong', $scores, 'Exact hit missing without cutoff. Scores: ' . print_r($scores, true));
        static::assertArrayHasKey('weak', $scores, 'Weak fuzzy hit should be present without a cutoff. Scores: ' . print_r($scores, true));
        static::assertGreaterThan($scores['weak'], $scores['strong'], 'Exact hit must outscore the weak fuzzy hit.');

        $cutoff = ($scores['strong'] + $scores['weak']) / 2;
        $systemConfig->set('core.search.minScore', $cutoff);

        $scores = $this->scoresByKey($ids, 'Heckenschere');

        static::assertArrayHasKey('strong', $scores, \sprintf('Exact hit should survive cutoff %.4f but was dropped. Scores: %s', $cutoff, print_r($scores, true)));
        static::assertArrayNotHasKey('weak', $scores, \sprintf('Weak fuzzy hit should be cut by minScore %.4f but survived. Scores: %s', $cutoff, print_r($scores, true)));
    }

    /**
     * @return \Generator<string, array{ids: IdsCollection, blogs: list<array<string, mixed>>, searchFields: list<string>, searchScores: array<string, int>, minScore: ?float, term: string, expectedFirst: ?string, mustNotContainKeys?: list<string>}>
     */
    public static function searchScenariosProvider(): \Generator
    {
        $ids = new IdsCollection();
        yield 'glued query din340 matches indexed "DIN 340"' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'a1-target', 'Bohrcraft DIN 340 HSS'),
                self::blog($ids, 'a1-other', 'Hammer Tool'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'din340',
            'expectedFirst' => 'a1-target',
        ];

        $ids = new IdsCollection();
        yield 'split query "DIN 340" matches indexed DIN340' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'a2-target', 'DIN340 Drill Bit'),
                self::blog($ids, 'a2-other', 'Hammer Tool'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'DIN 340',
            'expectedFirst' => 'a2-target',
        ];

        $ids = new IdsCollection();
        yield 'digit+letter glued query (Kleber601) matches split keyword' => [
            'ids' => $ids,
            'blogs' => [
                self::blogWithKeywords($ids, 'a3-target', 'Kleber Tool', ['601.1']),
                self::blog($ids, 'a3-other', 'Hammer Tool'),
            ],
            'searchFields' => ['name', 'customSearchKeywords'],
            'searchScores' => ['name' => 500, 'customSearchKeywords' => 1000],
            'minScore' => null,
            'term' => 'Kleber601',
            'expectedFirst' => 'a3-target',
        ];

        $ids = new IdsCollection();
        yield 'V8000ASR (glued) matches "V8000 ASR" in name' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'a4-target', 'V8000 ASR Cleaner'),
                self::blog($ids, 'a4-other', 'Random Blog'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'V8000ASR',
            'expectedFirst' => 'a4-target',
        ];

        $ids = new IdsCollection();
        yield '"Gr49" matches indexed "Gr.49"' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'a5-target', 'ANATOMIC BAU 500 Gr.49'),
                self::blog($ids, 'a5-other', 'Random Article'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Gr49',
            'expectedFirst' => 'a5-target',
        ];

        $ids = new IdsCollection();
        yield 'comma decimal 5,5 matches indexed "5,5 mm"' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'b1-target', 'Drill 5,5 mm HSS'),
                self::blog($ids, 'b1-other', 'Drill 2,5 mm HSS'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => '5,5',
            'expectedFirst' => 'b1-target',
        ];

        $ids = new IdsCollection();
        yield 'slash in spec 65/92 matches indexed "65/92/10"' => [
            'ids' => $ids,
            'blogs' => [
                self::blogWithKeywords($ids, 'b2-target', 'Multi-Point Lock', ['65/92/10']),
                self::blogWithKeywords($ids, 'b2-other', 'Multi-Point Lock', ['70/92/10']),
            ],
            'searchFields' => ['customSearchKeywords'],
            'searchScores' => ['customSearchKeywords' => 1000],
            'minScore' => null,
            'term' => '65/92',
            'expectedFirst' => 'b2-target',
        ];

        $ids = new IdsCollection();
        yield 'hyphenated HWS-112 in name matches query "HWS 112"' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'b3-target', 'Remmers HWS-112 Sealant'),
                self::blog($ids, 'b3-other', 'Remmers HWS-200 Sealant'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'HWS 112',
            'expectedFirst' => 'b3-target',
        ];

        $ids = new IdsCollection();
        yield 'hyphenated query "Cobra-Wasserpumpenzange" matches indexed Wasserpumpenzange' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'b4-target', 'Cobra Wasserpumpenzange K1462784'),
                self::blog($ids, 'b4-other', 'Basic Schraubenschlüssel'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Cobra-Wasserpumpenzange',
            'expectedFirst' => 'b4-target',
        ];

        $ids = new IdsCollection();
        yield 'lone G query does not hit blogs that only contain bare G from HSS-G' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'c1-hssg', 'HSS-G Drill Bit'),
                self::blog($ids, 'c1-other', 'Hammer Tool'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'G',
            'expectedFirst' => null,
            'mustNotContainKeys' => ['c1-hssg', 'c1-other'],
        ];

        $ids = new IdsCollection();
        yield 'query 5,5 does not also match blogs with lone bare 5 in name' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'c2-target', 'Bohrcraft 5,5 mm'),
                self::blog($ids, 'c2-bare5', 'Hammer 340g size 5'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => '5,5',
            'expectedFirst' => 'c2-target',
            'mustNotContainKeys' => ['c2-bare5'],
        ];

        $ids = new IdsCollection();
        yield 'short 4-char query "Baum" does not fuzzy-match "Baus"' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'f1-exact', 'Baum Tree Premium'),
                self::blog($ids, 'f1-fuzzy', 'Baus Haus Bau'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Baum',
            'expectedFirst' => 'f1-exact',
            'mustNotContainKeys' => ['f1-fuzzy'],
        ];

        $ids = new IdsCollection();
        yield 'prefix_length 2 rejects first-char-edit fuzzy match (Stihl != Spax)' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'f2-exact', 'Stihl Motorsäge'),
                self::blog($ids, 'f2-fuzzy', 'Spax Holzschraube'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Stihl',
            'expectedFirst' => 'f2-exact',
            'mustNotContainKeys' => ['f2-fuzzy'],
        ];

        $ids = new IdsCollection();
        yield 'exact "Mutter" outranks fuzzy candidate "Mütze"' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'f3-exact', 'Mutter Sechskant M8'),
                self::blog($ids, 'f3-fuzzy', 'Mütze Wintermütze Wolle'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Mutter',
            'expectedFirst' => 'f3-exact',
        ];

        $ids = new IdsCollection();
        yield '10-char token exact ranks far above fuzzy (prefix_length 3)' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'f4-exact', 'bohrcraftxz Exact'),
                self::blog($ids, 'f4-fuzzy', 'bxxrcraftxz Prefix'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'bohrcraftxz',
            'expectedFirst' => 'f4-exact',
        ];

        $ids = new IdsCollection();
        yield 'minScore=0 (default) returns weak fuzzy-only hit alongside strong hit' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'g1-strong', 'Heckenschere Professional'),
                self::blog($ids, 'g1-weak', 'Heckeschere Weak Variant'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => 0.0,
            'term' => 'Heckenschere',
            'expectedFirst' => 'g1-strong',
        ];

        $ids = new IdsCollection();
        yield 'repeated query token does not double-score the match (unique filter)' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'h1-target', 'Bohrcraft Drill'),
                self::blog($ids, 'h1-other', 'Bohrcraft Drill Set'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'bohrcraft bohrcraft',
            'expectedFirst' => null,
        ];

        $ids = new IdsCollection();
        yield 'Bohrcraft din340 5,5 - correct blog ranks first in a mixed catalog' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'i1-target', 'Bohrcraft Spibo DIN 340 HSS-G geschl. Split Point Typ N 5,5 mm Bohrcraft QP'),
                self::blog($ids, 'i1-only-brand', 'Bohrcraft Basic Hammer'),
                self::blog($ids, 'i1-only-size', 'Bohrer 5,5 mm Einzeln'),
                self::blog($ids, 'i1-only-din', 'DIN 340 generic drill'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Bohrcraft din340 5,5',
            'expectedFirst' => 'i1-target',
        ];

        $ids = new IdsCollection();
        yield '"variant vx 7539/160" fuzzy-matches indexed "VX 7939/160"' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'i2-target', 'variant VX 7939/160 compact'),
                self::blog($ids, 'i2-other', 'variant XYZ 1111/222 other'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'variant vx 7539/160',
            'expectedFirst' => 'i2-target',
        ];

        $ids = new IdsCollection();
        yield 'PascalCase ChannelLine reached via split_on_case_change' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'i3-target', 'ChannelLine Drill Premium'),
                self::blog($ids, 'i3-other', 'Basic Hammer'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Channel Line',
            'expectedFirst' => 'i3-target',
        ];

        $ids = new IdsCollection();
        yield 'compressed "3.3mm" matches indexed "3,3 mm"' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'j1-target', 'Bohrer 3,3 mm HSS'),
                self::blog($ids, 'j1-other', 'Hammer 5,5 mm Set'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => '3.3mm',
            'expectedFirst' => 'j1-target',
        ];

        $ids = new IdsCollection();
        yield 'compressed "100ml" matches indexed "100 ml"' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'j2-target', 'Bohrcraft 100 ml Spray'),
                self::blog($ids, 'j2-other', 'Basic 50 g Hammer'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => '100ml',
            'expectedFirst' => 'j2-target',
        ];

        $ids = new IdsCollection();
        yield 'over-glue protection: Gr.49 stays findable inside "Gr49 Gr.49 ChannelLine"' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'j3-target', 'Bohrcraft Gr49 Gr.49 ChannelLine Drill'),
                self::blog($ids, 'j3-other', 'Basic Hammer'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Gr.49',
            'expectedFirst' => 'j3-target',
        ];

        $ids = new IdsCollection();
        yield 'query "33 drill" finds "3,3 mm Drill" via catenate_numbers' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'j4-target', 'Bohrer 3,3 mm Drill HSS'),
                self::blog($ids, 'j4-other', 'Hammer 33 mm Set'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => '33 drill',
            'expectedFirst' => 'j4-target',
        ];

        $ids = new IdsCollection();
        yield 'query "Bohrcraft DIN 340 HSSG 5.00" finds indexed "Bohrcraft DIN 340 HSSG 5.0 mm"' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'j5-target', 'Bohrcraft DIN 340 HSSG 5.0 mm'),
                self::blog($ids, 'j5-other', 'Bohrcraft DIN 340 HSSG 6.0 mm'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Bohrcraft DIN 340 HSSG 5.00',
            'expectedFirst' => 'j5-target',
        ];

        $ids = new IdsCollection();
        yield 'query "Bohrcraft DIN 340 3.3" finds indexed "Bohrcraft DIN 340 3.30 mm"' => [
            'ids' => $ids,
            'blogs' => [
                self::blog($ids, 'j6-target', 'Bohrcraft DIN 340 3.30 mm Drill'),
                self::blog($ids, 'j6-other', 'Bohrcraft DIN 340 4.50 mm Drill'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Bohrcraft DIN 340 3.3',
            'expectedFirst' => 'j6-target',
        ];

        $ids = new IdsCollection();
        yield 'lengthNorm: focused customSearchKeywords outranks diluted list on shared token' => [
            'ids' => $ids,
            'blogs' => [
                self::blogWithKeywords($ids, 'k1-focused', 'Drill A', ['bohrcraft']),
                self::blogWithKeywords($ids, 'k1-diluted', 'Drill B', [
                    'bohrcraft', 'drill', 'tool', 'hammer', 'saw',
                    'wrench', 'pliers', 'set', 'kit', 'professional',
                ]),
            ],
            'searchFields' => ['customSearchKeywords'],
            'searchScores' => ['customSearchKeywords' => 1000],
            'minScore' => null,
            'term' => 'bohrcraft',
            'expectedFirst' => 'k1-focused',
        ];
    }

    public function testExactPhraseMatchRanksAheadOfScatteredWordMatch(): void
    {
        $this->clearElasticsearch();

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM blog');

        $ids = new IdsCollection();

        static::getContainer()->get('blog.repository')->create([
            self::blog($ids, 'phrase', 'Paper Rippers Special Edition Deluxe Collectors Bundle'),
            self::blog($ids, 'scattered', 'Rippers Paper'),
        ], Context::createDefaultContext());

        $this->setSearchConfiguration(true, ['name']);
        $this->setSearchScores(['name' => 700]);

        $this->indexElasticSearch();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('Paper Rippers');

        $definition = static::getContainer()->get(BlogDefinition::class);
        $result = $this->createEntitySearcher()->search($definition, $criteria, Context::createDefaultContext());

        $firstId = $result->firstId();
        static::assertNotNull($firstId, print_r($result->getData(), true));
        static::assertSame('phrase', $ids->getKey($firstId), print_r($result->getData(), true));
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    /**
     * @return array<string, float> live _score per IdsCollection key, for blogs matched by the term
     */
    private function scoresByKey(IdsCollection $ids, string $term): array
    {
        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm($term);

        $definition = static::getContainer()->get(BlogDefinition::class);
        $result = $this->createEntitySearcher()->search($definition, $criteria, Context::createDefaultContext());

        $scores = [];
        foreach ($result->getData() as $item) {
            $key = $ids->getKey((string) $item['id']);
            if ($key === null) {
                continue;
            }
            $scores[$key] = (float) $item['_score'];
        }

        return $scores;
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
     * @param list<string> $keywords
     *
     * @return array<string, mixed>
     */
    private static function blogWithKeywords(IdsCollection $ids, string $key, string $name, array $keywords): array
    {
        return new BlogBuilder($ids, $key)
            ->name($name)
            ->add('customSearchKeywords', $keywords)
            ->visibility()
            ->build();
    }
}
