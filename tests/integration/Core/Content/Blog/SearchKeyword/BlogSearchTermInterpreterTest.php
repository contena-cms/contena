<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\SearchKeyword;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigCollection;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchTermInterpreter;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchTermInterpreterInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Contena\Core\Framework\Util\ArrayNormalizer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class BlogSearchTermInterpreterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private BlogSearchTermInterpreterInterface $interpreter;

    /**
     * @var EntityRepository<BlogSearchConfigCollection>
     */
    private EntityRepository $blogSearchConfigRepository;

    private string $blogSearchConfigId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->interpreter = static::getContainer()->get(BlogSearchTermInterpreter::class);

        $this->blogSearchConfigRepository = static::getContainer()->get('blog_search_config.repository');
        $this->blogSearchConfigId = $this->getBlogSearchConfigId();

        $this->setupKeywords();
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('cases')]
    public function testMatching(string $term, array $expected): void
    {
        $context = Context::createDefaultContext();

        $matches = $this->interpreter->interpret($term, $context);

        $keywords = array_map(static fn (SearchTerm $term) => $term->getTerm(), $matches->getTerms());

        static::assertEqualsCanonicalizing(array_values($expected), array_values($keywords));
    }

    public function testNumericInputIsNotMatchingWithInfixPlaceholders(): void
    {
        $context = Context::createDefaultContext();

        $matches = $this->interpreter->interpret('1000', $context);

        $keywords = array_map(static fn (SearchTerm $term) => $term->getTerm(), $matches->getTerms());

        static::assertNotContains('10100', $keywords);
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('casesWithTokenFilter')]
    public function testMatchingWithTokenFilter(string $term, array $expected): void
    {
        $context = Context::createDefaultContext();

        $matches = $this->interpreter->interpret($term, $context);

        $keywords = array_map(static fn (SearchTerm $term) => $term->getTerm(), $matches->getTerms());

        static::assertEqualsCanonicalizing(array_values($expected), array_values($keywords));
    }

    /**
     * @param list<list<string>> $expected
     */
    #[DataProvider('caseWithFetchingTokenTerms')]
    public function testMatchingTokenTerms(string $term, array $expected): void
    {
        $context = Context::createDefaultContext();

        $tokenTerms = $this->interpreter->interpret($term, $context)->getTokenTerms();

        static::assertCount(\count($expected), $tokenTerms);
        foreach ($tokenTerms as $index => $tokenTerm) {
            static::assertEqualsCanonicalizing(array_values($expected[$index]), array_values($tokenTerm));
        }
    }

    #[DataProvider('caseWithMatchingBooleanCause')]
    public function testMatchingBooleanClause(bool $andLogic, string $expected): void
    {
        $context = Context::createDefaultContext();

        $this->blogSearchConfigRepository->update([
            ['id' => $this->blogSearchConfigId, 'andLogic' => $andLogic],
        ], $context);

        $matches = $this->interpreter->interpret('Random terms', $context);

        $booleanClause = $matches->getBooleanClause();

        static::assertSame($expected, $booleanClause);
    }

    #[DataProvider('caseWithMatchingSearchPatternTermLength')]
    public function testMatchingSearchPatternTermLength(bool $andLogic, string $words): void
    {
        $context = Context::createDefaultContext();

        $this->blogSearchConfigRepository->update([
            ['id' => $this->blogSearchConfigId, 'andLogic' => $andLogic],
        ], $context);

        $matches = $this->interpreter->interpret($words, $context);
        $terms = array_map(static fn (SearchTerm $term) => $term->getTerm(), $matches->getTerms());

        if (!$andLogic) {
            $flatterTerms = ArrayNormalizer::flatten($matches->getTokenTerms());

            static::assertLessThanOrEqual(\count($flatterTerms), \count($terms));
            static::assertLessThanOrEqual(8, \count($terms));

            return;
        }

        static::assertGreaterThanOrEqual(0, \count($terms));
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('termScoring')]
    public function testTermScoring(string $term, array $expected): void
    {
        $context = Context::createDefaultContext();

        $matches = $this->interpreter->interpret($term, $context);
        $terms = array_map(static fn (SearchTerm $term) => $term->getTerm(), $matches->getTerms());

        static::assertSame($expected, \array_slice($terms, 0, \count($expected)));
    }

    /**
     * @return array<array{0: string, 1: list<string>}>
     */
    public static function cases(): array
    {
        return [
            [
                'zeichn',
                ['zeichnet', 'zeichen', 'zweichnet'],
            ],
            [
                'zeichent',
                ['ausgezeichnet', 'gezeichnet', 'zeichnet'],
            ],
            [
                'Büronetz',
                ['büronetzwerk'],
            ],
            [
                '1000',
                ['10000', '10001', '10002', '10007'],
            ],
            [
                '9000',
                ['SW-9000'],
            ],
            'test it uses only first 8 keywords' => [
                '10',
                ['10', '100', '101', '102', '103', '10000', '10001', '10002'],
            ],
        ];
    }

    /**
     * @return array<array{0: string, 1: list<string>}>
     */
    public static function casesWithTokenFilter(): array
    {
        return [
            [
                'zeichn',
                ['zeichnet', 'zeichen', 'zweichnet'],
            ],
            [
                'zeichent',
                ['ausgezeichnet', 'gezeichnet', 'zeichnet'],
            ],
            [
                'Büronetz',
                ['büronetzwerk'],
            ],
            [
                '1000',
                ['10000', '10001', '10002', '10007'],
            ],
            [
                '1',
                [],
            ],
            [
                'between against in on',
                [],
            ],
            [
                'between against on in coffee bike',
                ['betweencoffee', 'betweenbike'],
            ],
        ];
    }

    /**
     * @return array<array{0: string, 1: list<list<string>>}>
     */
    public static function caseWithFetchingTokenTerms(): array
    {
        return [
            [
                'zeichn zeichent Büronetz',
                [
                    ['zeichnet', 'zeichen', 'zweichnet'],
                    ['ausgezeichnet', 'gezeichnet', 'zeichnet'],
                    ['büronetzwerk'],
                ],
            ],
            [
                'Büronetz 1000',
                [
                    ['büronetzwerk'],
                    ['10000', '10001', '10002', '10007'],
                ],
            ],
            [
                'Büronetz',
                [
                    ['büronetzwerk'],
                ],
            ],
            [
                'Büronetz 1',
                [
                    ['büronetzwerk'],
                ],
            ],
            [
                'against 1',
                [],
            ],
            [
                '2 1',
                [],
            ],
            [
                'zeichn zeichn',
                [
                    ['zeichnet', 'zeichen', 'zweichnet'],
                ],
            ],
            [
                '@##@$^zeichn$@#$#@ {}|=-!@#@!#zeichent[]-/\}{ ?"Büronetz?"',
                [
                    ['zeichnet', 'zeichen', 'zweichnet'],
                    ['ausgezeichnet', 'gezeichnet', 'zeichnet'],
                    ['büronetzwerk'],
                ],
            ],
            [
                '³²¼¼³¬½{¬]Büronetz³²¼¼³¬½{¬] ³²¼¼³¬½{¬]1000³²¼¼³¬½{¬]',
                [
                    ['büronetzwerk'],
                    ['10000', '10001', '10002', '10007'],
                ],
            ],
            [
                '¯\_(๑❛ᴗ❛๑)_/¯zeichn$¯\_(๑❛ᴗ❛๑)_/¯ ʚ(´◡`)zeichent(´◡`)ɞ ʚ(´◡`)Büronetz¯\_(๑❛ᴗ❛๑)_/¯',
                [
                    ['zeichnet', 'zeichen', 'zweichnet'],
                    ['ausgezeichnet', 'gezeichnet', 'zeichnet'],
                    ['büronetzwerk'],
                ],
            ],
            [
                '(๑★ .̫ ★๑)Büronet（★￣∀￣★） (̂ ˃̥̥̥ ˑ̫ ˂̥̥̥ )̂1000(*＾v＾*)',
                [
                    ['büronetzwerk'],
                    ['10000', '10001', '10002', '10007'],
                ],
            ],
            [
                '‰€€Büronet¥Æ ‡‡1000††',
                [
                    ['büronetzwerk'],
                    ['10000', '10001', '10002', '10007'],
                ],
            ],
        ];
    }

    /**
     * @return array<array{0: bool, 1: string}>
     */
    public static function caseWithMatchingBooleanCause(): array
    {
        return [
            [
                true,
                SearchPattern::BOOLEAN_CLAUSE_AND,
            ],
            [
                false,
                SearchPattern::BOOLEAN_CLAUSE_OR,
            ],
        ];
    }

    /**
     * @return array<array{0: bool, 1: string}>
     */
    public static function caseWithMatchingSearchPatternTermLength(): array
    {
        return [
            [
                true,
                'zeichn zeichent Büronetz 1000',
            ],
            [
                true,
                'zeichn zeichent 1000',
            ],
            [
                true,
                'zeichn 1 2',
            ],
            [
                true,
                '1 2',
            ],
            [
                true,
                'again 2',
            ],
            [
                false,
                'zeichn zeichent Büronetz 1000',
            ],
            [
                false,
                'zeichn zeichent 1000',
            ],
            [
                false,
                'zeichn 1 2',
            ],
            [
                false,
                '1 2',
            ],
            [
                false,
                'again 2',
            ],
        ];
    }

    /**
     * @return array<array{0: string, 1: list<string>}>
     */
    public static function termScoring(): array
    {
        return [
            [
                'Sessel',
                [
                    'Sessel',
                ],
            ],
            [
                'Gelber Sessel',
                [
                    'Gelber Sessel',
                    'Gelber Camping Sessel',
                    'Klappbarer gelber Camping Sessel',
                    'Klappbarer gelber Sessel',
                ],
            ],
            [
                'Klappbarer Camping Sessel',
                [
                    'Klappbarer Camping Sessel',
                    'Klappbarer blauer Camping Sessel',
                    'Klappbarer gelber Camping Sessel',
                    'Klappbarer roter Camping Sessel',
                ],
            ],
        ];
    }

    private function setupKeywords(): void
    {
        $keywords = [
            'zeichnet',
            'zweichnet',
            'ausgezeichnet',
            'verkehrzeichennetzwerk',
            'gezeichnet',
            'zeichen',
            'zweideutige',
            'zweier',
            'zweite',
            'zweiteilig',
            'zweiten',
            'zweites',
            'zweiweg',
            'zweifellos',
            'büronetzwerk',
            'heimnetzwerk',
            'netzwerk',
            'netzwerkadapter',
            'netzwerkbuchse',
            'netzwerkcontroller',
            'netzwerkdrucker',
            'netzwerke',
            'netzwerken',
            'netzwerkinfrastruktur',
            'netzwerkkabel',
            'netzwerkkabels',
            'netzwerkkarte',
            'netzwerklösung',
            'netzwerkschnittstelle',
            'netzwerkschnittstellen',
            'netzwerkspeicher',
            'netzwerkspeicherlösung',
            'netzwerkspieler',
            'schwarzweiß',
            'netzwerkprotokolle',
            'SW-9000',
            '10100',
            '10000',
            '10001',
            '10002',
            '10007',
            '10',
            '100',
            '101',
            '102',
            '103',
            '2',
            '3',
            'between',
            'against',
            'betweencoffee',
            'betweenbike',
            'Sessel',
            'Roter Camping Sessel',
            'Klappbarer roter Sessel',
            'Roter Sessel',
            'Klappbarer roter Camping Sessel',
            'Gelber Camping Sessel',
            'Klappbarer gelber Sessel',
            'Gelber Sessel',
            'Klappbarer gelber Camping Sessel',
            'Blauer Camping Sessel',
            'Klappbarer blauer Sessel',
            'Blauer Sessel',
            'Klappbarer blauer Camping Sessel',
            'Camping Sessel',
            'Klappbarer Camping Sessel',
        ];

        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        foreach ($keywords as $keyword) {
            $this->connection->insert('blog_keyword_dictionary', [
                'id' => Uuid::randomBytes(),
                'keyword' => $keyword,
                'language_id' => $languageId,
            ]);
        }
    }

    private function getBlogSearchConfigId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('languageId', Defaults::LANGUAGE_SYSTEM)
        );

        $id = $this->blogSearchConfigRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNotNull($id);

        return $id;
    }
}
