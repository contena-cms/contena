<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Blog;

use OpenSearchDSL\Query\Compound\BoolQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTag\BlogTagDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\CustomField\CustomFieldService;
use Contena\Core\System\Tag\TagDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Elasticsearch\Blog\AbstractBlogSearchQueryBuilder;
use Contena\Elasticsearch\Blog\BlogSearchQueryBuilder;
use Contena\Elasticsearch\ElasticsearchException;
use Contena\Elasticsearch\ExplainFieldQueryBuilder;
use Contena\Elasticsearch\FieldQueryBuilder;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchTokenizer;
use Contena\Elasticsearch\NestedFieldQueryBuilder;
use Contena\Elasticsearch\TokenQueryBuilder;
use Contena\Elasticsearch\TranslatedFieldQueryBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(AbstractBlogSearchQueryBuilder::class)]
#[CoversClass(BlogSearchQueryBuilder::class)]
class BlogSearchQueryBuilderTest extends TestCase
{
    private const SECOND_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20c';

    private TokenQueryBuilder $tokenQueryBuilder;

    protected function setUp(): void
    {
        $this->tokenQueryBuilder = new TokenQueryBuilder(
            $this->getRegistry(),
            new CustomFieldServiceStub([
                'evolvesInt' => new IntField('evolvesInt', 'evolvesInt'),
                'evolvesFloat' => new FloatField('evolvesFloat', 'evolvesFloat'),
                'evolvesText' => new StringField('evolvesText', 'evolvesText'),
            ]),
            new ExplainFieldQueryBuilder(
                new NestedFieldQueryBuilder(
                    new TranslatedFieldQueryBuilder(
                        new FieldQueryBuilder(),
                    ),
                ),
            ),
        );
    }

    public function testBuildEmptyQuery(): void
    {
        $this->expectExceptionObject(ElasticsearchException::emptyQuery());

        $builder = $this->getBuilder([
            self::config(field: 'autoIncrement', ranking: 500, tokenize: true, and: false),
        ]);

        $criteria = new Criteria();
        $criteria->setTerm('foo');
        $parsed = $builder->build($criteria, Context::createDefaultContext());

        static::assertSame([], $parsed->toArray());
    }

    public function testBuildWithoutFields(): void
    {
        $this->expectExceptionObject(ElasticsearchException::emptyQuery());

        $builder = $this->getBuilder(null);

        $criteria = new Criteria();

        $parsed = $builder->build($criteria, Context::createDefaultContext());

        static::assertSame([], $parsed->toArray());
    }

    /**
     * @param list<array{and_logic: string, field: string, tokenize: int, ranking: float, use_exact_subfield: int}> $config
     * @param array<string, mixed> $expected
     */
    #[DataProvider('buildSingleLanguageProvider')]
    public function testBuildSingleLanguage(array $config, string $term, array $expected): void
    {
        $builder = $this->getBuilder($config);

        $criteria = new Criteria();
        $criteria->setTerm($term);

        $parsed = $builder->build($criteria, Context::createDefaultContext());

        static::assertEquals($expected, $parsed->toArray());
    }

    /**
     * @param list<array{and_logic: string, field: string, tokenize: int, ranking: float, use_exact_subfield: int}> $config
     * @param array<string, mixed> $expected
     */
    #[DataProvider('buildMultipleLanguageProvider')]
    public function testBuildMultipleLanguages(array $config, string $term, array $expected): void
    {
        $builder = $this->getBuilder($config);

        $criteria = new Criteria();
        $criteria->setTerm($term);

        $context = new Context(
            new SystemSource(),
            [Defaults::LANGUAGE_SYSTEM, self::SECOND_LANGUAGE_ID],
        );

        $parsed = $builder->build($criteria, $context);

        static::assertEquals($expected, $parsed->toArray());
    }

    /**
     * @return iterable<array-key, array{config: list<array{and_logic: string, field: string, tokenize: int, ranking: float, use_exact_subfield: int}>, term: string, expected: array<string, mixed>}>
     */
    public static function buildSingleLanguageProvider(): iterable
    {
        $prefix = 'customFields.' . Defaults::LANGUAGE_SYSTEM . '.';
        yield 'Test tokenized fields' => [
            'config' => [
                self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
                self::config(field: 'tags.name', ranking: 500, tokenize: true, and: false),
            ],
            'term' => 'foo',
            'expected' => self::bool([
                self::disMax([
                    self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 2),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4, 'AUTO:5,10', 'or', 5),
                    self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
                ], 1000),
                self::nested('tags', self::disMax([
                    self::exactAnalyzed('tags.name.search', 'foo', 2),
                    self::match('tags.name.search', 'foo', 0.4, 'AUTO:5,10', 'or', 5),
                    self::prefix('tags.name.search', 'foo', 0.4),
                ], 500)),
            ]),
        ];

        yield 'Test single term with numeric field' => [
            'config' => [
                self::config(field: 'autoIncrement', ranking: 1000),
            ],
            'term' => '2023',
            'expected' => self::term('autoIncrement', 2023, 1000),
        ];

        yield 'Test multiple fields with terms' => [
            'config' => [
                self::config(field: 'name', ranking: 1000),
                self::config(field: 'autoIncrement', ranking: 1500),
                self::config(field: 'tags.name', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => self::boolMustShould(
                [
                    self::bool([
                        self::disMax([
                            self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 2),
                            self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4, 'AUTO:5,10', 'and', 5),
                            self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
                        ], 1000),
                        self::nested('tags', self::disMax([
                            self::exactAnalyzed('tags.name.search', 'foo', 2),
                            self::match('tags.name.search', 'foo', 0.4, 'AUTO:5,10', 'and', 5),
                            self::prefix('tags.name.search', 'foo', 0.4),
                        ], 500)),
                    ]),
                    self::bool([
                        self::disMax([
                            self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 2),
                            self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 0.4, 0, 'and', 10),
                            self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 0.4),
                        ], 1000),
                        self::term('autoIncrement', 2023, 1500),
                        self::nested('tags', self::disMax([
                            self::exactAnalyzed('tags.name.search', '2023', 2),
                            self::match('tags.name.search', '2023', 0.4, 0, 'and', 10),
                            self::prefix('tags.name.search', '2023', 0.4),
                        ], 500)),
                    ]),
                ],
                [
                    self::bool([
                        self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 4.0 * 1000, 3, 10),
                        self::nested('tags', self::matchPhrasePrefix('tags.name.search', 'foo 2023', 4.0 * 500, 3, 10)),
                    ]),
                ],
            ),
        ];

        yield 'Test multiple custom fields with terms' => [
            'config' => [
                self::config(field: 'customFields.evolvesText', ranking: 500),
                self::config(field: 'customFields.evolvesInt', ranking: 400),
                self::config(field: 'customFields.evolvesFloat', ranking: 500),
                self::config(field: 'categories.childCount', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => self::boolMustShould(
                [
                    self::disMax([
                        self::exactAnalyzed($prefix . 'evolvesText.search', 'foo', 2),
                        self::match($prefix . 'evolvesText.search', 'foo', 0.4, 'AUTO:5,10', 'and', 5),
                        self::prefix($prefix . 'evolvesText.search', 'foo', 0.4),
                    ], 500),
                    self::bool([
                        self::disMax([
                            self::exactAnalyzed($prefix . 'evolvesText.search', '2023', 2),
                            self::match($prefix . 'evolvesText.search', '2023', 0.4, 0, 'and', 10),
                            self::prefix($prefix . 'evolvesText.search', '2023', 0.4),
                        ], 500),
                        self::term($prefix . 'evolvesInt', 2023, 400),
                        self::term($prefix . 'evolvesFloat', 2023.0, 500),
                        self::nested('categories', self::term('categories.childCount', 2023, 500)),
                    ]),
                ],
                [
                    self::matchPhrasePrefix($prefix . 'evolvesText.search', 'foo 2023', 4.0 * 500, 3, 10),
                ],
            ),
        ];
    }

    /**
     * @return iterable<array-key, array{config: list<array{and_logic: string, field: string, tokenize: int, ranking: float, use_exact_subfield: int}>, term: string, expected: array<string, mixed>}>
     */
    public static function buildMultipleLanguageProvider(): iterable
    {
        $prefixCfLang1 = 'customFields.' . Defaults::LANGUAGE_SYSTEM . '.';
        $prefixCfLang2 = 'customFields.' . self::SECOND_LANGUAGE_ID . '.';
        yield 'Test tokenized fields' => [
            'config' => [
                self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
                self::config(field: 'tags.name', ranking: 500, tokenize: true, and: false),
                self::config(field: 'categories.name', ranking: 200, tokenize: true, and: false),
            ],
            'term' => 'foo',
            'expected' => self::bool([
                self::disMax([
                    self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 2),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4, 'AUTO:5,10', 'or', 5),
                    self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
                ], 1000),
                self::nested('tags', self::disMax([
                    self::exactAnalyzed('tags.name.search', 'foo', 2),
                    self::match('tags.name.search', 'foo', 0.4, 'AUTO:5,10', 'or', 5),
                    self::prefix('tags.name.search', 'foo', 0.4),
                ], 500)),
                self::nested('categories', self::disMax([
                    self::disMax([
                        self::exactAnalyzed('categories.name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 2),
                        self::match('categories.name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4, 'AUTO:5,10', 'or', 5),
                        self::prefix('categories.name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
                    ], 200),
                    self::disMax([
                        self::exactAnalyzed('categories.name.' . self::SECOND_LANGUAGE_ID . '.search', 'foo', 2),
                        self::match('categories.name.' . self::SECOND_LANGUAGE_ID . '.search', 'foo', 0.4, 'AUTO:5,10', 'or', 5),
                        self::prefix('categories.name.' . self::SECOND_LANGUAGE_ID . '.search', 'foo', 0.4),
                    ], 160),
                ])),
            ]),
        ];

        yield 'Test multiple fields with terms' => [
            'config' => [
                self::config(field: 'name', ranking: 1000),
                self::config(field: 'autoIncrement', ranking: 1500),
                self::config(field: 'tags.name', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => self::boolMustShould(
                [
                    self::bool([
                        self::disMax([
                            self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 2),
                            self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4, 'AUTO:5,10', 'and', 5),
                            self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
                        ], 1000),
                        self::nested('tags', self::disMax([
                            self::exactAnalyzed('tags.name.search', 'foo', 2),
                            self::match('tags.name.search', 'foo', 0.4, 'AUTO:5,10', 'and', 5),
                            self::prefix('tags.name.search', 'foo', 0.4),
                        ], 500)),
                    ]),
                    self::bool([
                        self::disMax([
                            self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 2),
                            self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 0.4, 0, 'and', 10),
                            self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 0.4),
                        ], 1000),
                        self::term('autoIncrement', 2023, 1500),
                        self::nested('tags', self::disMax([
                            self::exactAnalyzed('tags.name.search', '2023', 2),
                            self::match('tags.name.search', '2023', 0.4, 0, 'and', 10),
                            self::prefix('tags.name.search', '2023', 0.4),
                        ], 500)),
                    ]),
                ],
                [
                    self::bool([
                        self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 4.0 * 1000, 3, 10),
                        self::nested('tags', self::matchPhrasePrefix('tags.name.search', 'foo 2023', 4.0 * 500, 3, 10)),
                    ]),
                ],
            ),
        ];

        yield 'Test multiple custom fields with terms' => [
            'config' => [
                self::config(field: 'customFields.evolvesText', ranking: 500),
                self::config(field: 'customFields.evolvesInt', ranking: 400),
                self::config(field: 'customFields.evolvesFloat', ranking: 500),
                self::config(field: 'categories.childCount', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => self::boolMustShould(
                [
                    self::disMax([
                        self::disMax([
                            self::exactAnalyzed($prefixCfLang1 . 'evolvesText.search', 'foo', 2),
                            self::match($prefixCfLang1 . 'evolvesText.search', 'foo', 0.4, 'AUTO:5,10', 'and', 5),
                            self::prefix($prefixCfLang1 . 'evolvesText.search', 'foo', 0.4),
                        ], 500),
                        self::disMax([
                            self::exactAnalyzed($prefixCfLang2 . 'evolvesText.search', 'foo', 2),
                            self::match($prefixCfLang2 . 'evolvesText.search', 'foo', 0.4, 'AUTO:5,10', 'and', 5),
                            self::prefix($prefixCfLang2 . 'evolvesText.search', 'foo', 0.4),
                        ], 400),
                    ]),
                    self::bool([
                        self::disMax([
                            self::disMax([
                                self::exactAnalyzed($prefixCfLang1 . 'evolvesText.search', '2023', 2),
                                self::match($prefixCfLang1 . 'evolvesText.search', '2023', 0.4, 0, 'and', 10),
                                self::prefix($prefixCfLang1 . 'evolvesText.search', '2023', 0.4),
                            ], 500),
                            self::disMax([
                                self::exactAnalyzed($prefixCfLang2 . 'evolvesText.search', '2023', 2),
                                self::match($prefixCfLang2 . 'evolvesText.search', '2023', 0.4, 0, 'and', 10),
                                self::prefix($prefixCfLang2 . 'evolvesText.search', '2023', 0.4),
                            ], 400),
                        ]),
                        self::disMax([
                            self::term($prefixCfLang1 . 'evolvesInt', 2023, 400),
                            self::term($prefixCfLang2 . 'evolvesInt', 2023, 320),
                        ]),
                        self::disMax([
                            self::term($prefixCfLang1 . 'evolvesFloat', 2023, 500),
                            self::term($prefixCfLang2 . 'evolvesFloat', 2023, 400),
                        ]),
                        self::nested('categories', self::term('categories.childCount', 2023, 500)),
                    ]),
                ],
                [
                    self::disMax([
                        self::matchPhrasePrefix($prefixCfLang1 . 'evolvesText.search', 'foo 2023', 4.0 * 500, 3, 10),
                        self::matchPhrasePrefix($prefixCfLang2 . 'evolvesText.search', 'foo 2023', 4.0 * 400, 3, 10),
                    ]),
                ],
            ),
        ];
    }

    public function testMultiTokenBuildsAndGateWithPhraseBoost(): void
    {
        $builder = $this->getBuilder([
            self::config(field: 'name', ranking: 1000, tokenize: true, and: true),
            self::config(field: 'description', ranking: 2000, tokenize: true, and: true),
        ]);

        $criteria = new Criteria();
        $criteria->setTerm('foo 2023');

        $parsed = $builder->build($criteria, Context::createDefaultContext());
        $queryArray = $parsed->toArray();

        // AND gate: every token MUST match; the phrase is an additive SHOULD boost.
        static::assertArrayHasKey('bool', $queryArray);
        static::assertCount(2, $queryArray['bool']['must']);
        static::assertCount(1, $queryArray['bool']['should']);

        // The phrase boost carries only match_phrase_prefix clauses (no per-token exact/fuzzy,
        // and no DisMax wrapper — the field ranking is folded into the clause boost).
        $phraseQuery = $queryArray['bool']['should'][0];
        static::assertArrayHasKey('bool', $phraseQuery);
        foreach ($phraseQuery['bool']['should'] as $fieldQuery) {
            static::assertArrayHasKey('match_phrase_prefix', $fieldQuery);
        }
    }

    public function testOrMultiWordSearchDisablesNgram(): void
    {
        // n-gram (substring) matching is noise for OR multi-word: "line" must not match inside
        // "Portaline". Parity with the pre-refactor behaviour, where the whole OR term was passed
        // as one token and the n-gram clause was suppressed.
        $builder = $this->getBuilder([
            self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
        ]);

        $criteria = new Criteria();
        $criteria->setTerm('channel line');

        $query = $builder->build($criteria, Context::createDefaultContext());

        static::assertStringNotContainsString('.ngram', json_encode($query->toArray(), \JSON_THROW_ON_ERROR));
    }

    public function testOrSingleWordSearchKeepsNgram(): void
    {
        $builder = $this->getBuilder([
            self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
        ]);

        $criteria = new Criteria();
        $criteria->setTerm('channel');

        $query = $builder->build($criteria, Context::createDefaultContext());

        static::assertStringContainsString('.ngram', json_encode($query->toArray(), \JSON_THROW_ON_ERROR));
    }

    public function testAndMultiWordSearchKeepsNgram(): void
    {
        // AND already required every word to match, so per-word substring matching stays on
        // (unchanged from before the refactor).
        $builder = $this->getBuilder([
            self::config(field: 'name', ranking: 1000, tokenize: true, and: true),
        ]);

        $criteria = new Criteria();
        $criteria->setTerm('channel line');

        $query = $builder->build($criteria, Context::createDefaultContext());

        static::assertStringContainsString('.ngram', json_encode($query->toArray(), \JSON_THROW_ON_ERROR));
    }

    public function testTranslatedSingleTokenExactMatchUsesExactSubfieldWhenConfigured(): void
    {
        $builder = $this->getBuilder([
            self::config(field: 'name', ranking: 1000, useExactSubfield: true),
        ]);

        $criteria = new Criteria();
        $criteria->setTerm('foo');

        $parsed = $builder->build($criteria, Context::createDefaultContext());

        static::assertStringContainsString(
            '"name.' . Defaults::LANGUAGE_SYSTEM . '.exact"',
            json_encode($parsed->toArray(), \JSON_THROW_ON_ERROR),
        );
    }

    public function testTranslatedPhraseBoostUsesSearchSubfield(): void
    {
        $builder = $this->getBuilder([
            self::config(field: 'name', ranking: 1000, useExactSubfield: true),
        ]);

        $criteria = new Criteria();
        $criteria->setTerm('foo bar');

        $parsed = $builder->build($criteria, Context::createDefaultContext());
        $queryArray = $parsed->toArray();

        // Per-token queries still use the exact keyword subfield when configured ...
        static::assertStringContainsString(
            '"name.' . Defaults::LANGUAGE_SYSTEM . '.exact"',
            json_encode($queryArray['bool']['must'], \JSON_THROW_ON_ERROR),
        );

        // ... but the phrase boost runs on the analyzed .search subfield via match_phrase_prefix.
        $phraseQuery = $queryArray['bool']['should'][0];
        static::assertStringContainsString(
            'match_phrase_prefix',
            json_encode($phraseQuery, \JSON_THROW_ON_ERROR),
        );
        static::assertStringContainsString(
            '"name.' . Defaults::LANGUAGE_SYSTEM . '.search"',
            json_encode($phraseQuery, \JSON_THROW_ON_ERROR),
        );
    }

    public function testDecoration(): void
    {
        $builder = new BlogSearchQueryBuilder(
            $this->getDefinition(),
            static::createStub(TokenFilter::class),
            static::createStub(SearchConfigLoader::class),
            $this->tokenQueryBuilder,
            new ElasticsearchTokenizer(),
        );

        static::expectException(DecorationPatternException::class);
        $builder->getDecorated();
    }

    private function getDefinition(): EntityDefinition
    {
        $instanceRegistry = $this->getRegistry();

        return $instanceRegistry->getByEntityName('blog');
    }

    private function getRegistry(): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            [
                BlogDefinition::class,
                BlogTagDefinition::class,
                TagDefinition::class,
                BlogTranslationDefinition::class,
                CategoryTranslationDefinition::class,
                BlogCategoryDefinition::class,
                CategoryDefinition::class,
                CategoryTranslationDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    /**
     * @param list<array{and_logic: string, field: string, tokenize: int, ranking: float, use_exact_subfield: int}>|null $config
     */
    private function getBuilder(?array $config): BlogSearchQueryBuilder
    {
        $configLoader = static::createStub(SearchConfigLoader::class);
        $configLoader->method('load')->willReturn($config ?? []);

        $tokenFilter = static::createStub(AbstractTokenFilter::class);
        $tokenFilter->method('filter')->willReturnArgument(0);

        return new BlogSearchQueryBuilder(
            $this->getDefinition(),
            $tokenFilter,
            $configLoader,
            $this->tokenQueryBuilder,
            new ElasticsearchTokenizer(),
        );
    }

    /**
     * @return array{and_logic: string, field: string, tokenize: int, ranking: float, use_exact_subfield: int}
     */
    private static function config(string $field, float $ranking, bool $tokenize = false, bool $and = true, bool $useExactSubfield = false): array
    {
        return [
            'and_logic' => $and ? '1' : '0',
            'field' => $field,
            'tokenize' => $tokenize ? 1 : 0,
            'ranking' => $ranking,
            'use_exact_subfield' => $useExactSubfield ? 1 : 0,
        ];
    }

    /**
     * @return array{term: array<string, array{value: string|int|float, boost: float}>}
     */
    private static function term(string $field, string|int|float $query, float $boost): array
    {
        return [
            'term' => [
                $field => [
                    'boost' => $boost,
                    'value' => $query,
                ],
            ],
        ];
    }

    /**
     * @return array{match: array<string, array{query: string|int|float, boost: float, fuzziness: int, operator: string}>}
     */
    private static function exactAnalyzed(string $field, string|int|float $query, float $boost): array
    {
        return [
            'match' => [
                $field => [
                    'query' => $query,
                    'boost' => $boost,
                    'fuzziness' => 0,
                    'operator' => 'and',
                ],
            ],
        ];
    }

    /**
     * @param array<mixed> $query
     *
     * @return array{nested: array{path: string, query: array<mixed>}}
     */
    private static function nested(string $root, array $query): array
    {
        return [
            'nested' => [
                'path' => $root,
                'query' => $query,
            ],
        ];
    }

    /**
     * @return array{match: array<string, array{query: string|int|float, boost: float, operator: string, fuzzy_transpositions: bool, prefix_length: int, fuzziness?: int|string, max_expansions?: int}>}
     */
    private static function match(string $field, string|int|float $query, int|float $boost, int|string|null $fuzziness = null, string $operator = 'and', ?int $maxExpansions = null): array
    {
        $payload = [
            'query' => $query,
            'boost' => (float) $boost,
            'fuzziness' => $fuzziness,
            'operator' => $operator,
            'fuzzy_transpositions' => true,
            'max_expansions' => $maxExpansions,
            'prefix_length' => mb_strlen((string) $query) >= 10 ? 3 : 2,
        ];

        return [
            'match' => [
                $field => array_filter($payload, static fn ($value) => $value !== null),
            ],
        ];
    }

    /**
     * @param array<mixed> $queries
     *
     * @return array{dis_max: array{queries: array<mixed>, boost?: float, tie_breaker?: float}}
     */
    private static function disMax(array $queries, float|int|null $boost = null, ?float $tieBreaker = 0.2): array
    {
        $payload = [
            'queries' => $queries,
        ];

        if ($boost !== null) {
            $payload['boost'] = (float) $boost;
        }

        if ($tieBreaker !== null) {
            $payload['tie_breaker'] = $tieBreaker;
        }

        return [
            'dis_max' => $payload,
        ];
    }

    /**
     * @param array<mixed> $queries
     *
     * @return array{ bool: array<string, array<mixed>> }
     */
    private static function bool(array $queries, string $operator = BoolQuery::SHOULD): array
    {
        return [
            'bool' => [
                $operator => $queries,
            ],
        ];
    }

    /**
     * @param array<mixed> $must
     * @param array<mixed> $should
     *
     * @return array{bool: array<string, array<mixed>>}
     */
    private static function boolMustShould(array $must, array $should): array
    {
        return [
            'bool' => [
                BoolQuery::MUST => $must,
                BoolQuery::SHOULD => $should,
            ],
        ];
    }

    /**
     * @return array{match_bool_prefix: array<string, array{query: string|int|float, boost: float}>}
     */
    private static function prefix(string $field, string|int|float $query, float $boost): array
    {
        return [
            'match_bool_prefix' => [
                $field => [
                    'query' => $query,
                    'boost' => $boost,
                ],
            ],
        ];
    }

    /**
     * @return array{match_phrase_prefix: array<string, array{query: string|int|float, boost: float, slop: int, max_expansions: int}>}
     */
    private static function matchPhrasePrefix(string $field, string|int|float $query, float $boost, int $slop = 3, int $maxExpansions = 10): array
    {
        return [
            'match_phrase_prefix' => [
                $field => [
                    'query' => $query,
                    'boost' => $boost,
                    'slop' => $slop,
                    'max_expansions' => $maxExpansions,
                ],
            ],
        ];
    }
}

/**
 * @internal
 */
class CustomFieldServiceStub extends CustomFieldService
{
    /**
     * @internal
     *
     * @param array<string, Field> $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function getCustomField(string $attributeName): Field
    {
        return $this->config[$attributeName];
    }
}
