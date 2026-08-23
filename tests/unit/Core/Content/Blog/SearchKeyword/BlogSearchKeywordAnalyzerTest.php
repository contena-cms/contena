<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\SearchKeyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\SearchKeyword\AnalyzedKeyword;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchKeywordAnalyzer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface;
use Contena\Core\System\Tag\TagCollection;
use Contena\Core\System\Tag\TagEntity;

/**
 * @internal
 */
#[CoversClass(BlogSearchKeywordAnalyzer::class)]
class BlogSearchKeywordAnalyzerTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    /**
     * @param array<string, mixed> $blogData
     * @param array<int, array{field: string, tokenize: bool, ranking: int}> $configFields
     * @param list<int|string> $expected
     */
    #[DataProvider('analyzeCases')]
    public function testAnalyze(array $blogData, array $configFields, array $expected): void
    {
        $blog = new BlogEntity();
        $blog->assign($blogData);

        $tokenizer = new Tokenizer(['-', '_']);
        $tokenFilter = static::createStub(TokenFilter::class);
        $tokenFilter->method('filter')->willReturnCallback(static fn (array $tokens): array => $tokens);
        $configLoader = static::createStub(SearchConfigLoader::class);
        $configLoader->method('load')->willReturn([['min_search_length' => 3]]);

        $result = new BlogSearchKeywordAnalyzer($tokenizer, $tokenFilter, $configLoader)
            ->analyze($blog, $this->context, $configFields)
            ->getKeys();

        sort($result);
        sort($expected);

        static::assertEquals($expected, $result);
    }

    /**
     * @param array<string, mixed> $blogData
     * @param array<int, array{field: string, tokenize: bool, ranking: int}> $configFields
     * @param list<int|string> $expected
     */
    #[DataProvider('analyzeCases')]
    public function testAnalyzeWithIgnoredErrorNoticeReporting(array $blogData, array $configFields, array $expected): void
    {
        $oldLevel = error_reporting(\E_ERROR);

        try {
            $this->testAnalyze($blogData, $configFields, $expected);
        } finally {
            error_reporting($oldLevel);
        }
    }

    /**
     * @return \Generator<string, array{0: array<string, mixed>, 1: array<int, array{field: string, tokenize: bool, ranking: int}>, 2: list<int|string>}>
     */
    public static function analyzeCases(): \Generator
    {
        $tag1 = new TagEntity();
        $tag1->setId('tag-1');
        $tag1->setName('Tag Yellow');
        $tag2 = new TagEntity();
        $tag2->setId('tag-2');
        $tag2->setName('Tag Pink');
        $tags = new TagCollection([$tag1, $tag2]);

        yield 'analyze with tokenize' => [
            [
                'keywords' => 'MANU_001',
                'description' => self::getLongTextDescription(),
                'tags' => $tags,
                'translated' => ['name' => 'Awesome blog'],
            ],
            [
                ['field' => 'keywords', 'tokenize' => true, 'ranking' => 100],
                ['field' => 'description', 'tokenize' => true, 'ranking' => 100],
                ['field' => 'tags.name', 'tokenize' => true, 'ranking' => 100],
                ['field' => 'name', 'tokenize' => true, 'ranking' => 100],
            ],
            [
                'awesome', 'awesome blog', 'blog', 'description', 'long', 'manu_001',
                'pink', 'tag', 'tag yellow pink', 'this', 'this long description', 'yellow',
            ],
        ];

        yield 'analyze without tokenize' => [
            [
                'keywords' => 'MANU_001',
                'description' => self::getLongTextDescription(),
                'tags' => $tags,
                'translated' => ['name' => 'Awesome blog'],
            ],
            [
                ['field' => 'keywords', 'tokenize' => false, 'ranking' => 100],
                ['field' => 'description', 'tokenize' => false, 'ranking' => 100],
                ['field' => 'tags.name', 'tokenize' => false, 'ranking' => 100],
                ['field' => 'name', 'tokenize' => true, 'ranking' => 100],
            ],
            [
                'MANU_001', 'Tag Pink', 'Tag Yellow', self::getLongTextPart1(), self::getLongTextPart2(),
                'awesome', 'blog', 'awesome blog',
            ],
        ];

        yield 'analyze nested custom field arrays' => [
            [
                'customFields' => [
                    'flat' => ['part-a', 'part-b'],
                    'nested' => ['part-a' => ['a1', 'a2'], 'part-b' => ['b1', 'b2']],
                ],
                'translated' => ['name' => 'Awesome blog'],
            ],
            [
                ['field' => 'customFields.flat', 'tokenize' => true, 'ranking' => 100],
                ['field' => 'customFields.nested', 'tokenize' => true, 'ranking' => 100],
                ['field' => 'name', 'tokenize' => true, 'ranking' => 100],
            ],
            ['awesome', 'blog', 'part-a', 'part-b', 'awesome blog', 'part-a part-b'],
        ];
    }

    public function testAssociativeArrayOrderIndependence(): void
    {
        $tokenizer = static::createStub(TokenizerInterface::class);
        $tokenizer->method('tokenize')->willReturnCallback(static fn (string $text): array => explode(' ', $text));
        $tokenFilter = static::createStub(AbstractTokenFilter::class);
        $tokenFilter->method('filter')->willReturnArgument(0);
        $configLoader = static::createStub(SearchConfigLoader::class);
        $configLoader->method('load')->willReturn([['min_search_length' => 3]]);
        $analyzer = new BlogSearchKeywordAnalyzer($tokenizer, $tokenFilter, $configLoader);
        $config = [['field' => 'customFields.assocArray', 'tokenize' => true, 'ranking' => 100]];

        $blog1 = new BlogEntity();
        $blog1->setCustomFields(['assocArray' => ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3']]);
        $blog2 = new BlogEntity();
        $blog2->setCustomFields(['assocArray' => ['key3' => 'value3', 'key1' => 'value1', 'key2' => 'value2']]);

        $words1 = $analyzer->analyze($blog1, $this->context, $config)->map(static fn (AnalyzedKeyword $keyword): string => $keyword->getKeyword());
        $words2 = $analyzer->analyze($blog2, $this->context, $config)->map(static fn (AnalyzedKeyword $keyword): string => $keyword->getKeyword());
        sort($words1);
        sort($words2);

        static::assertSame($words1, $words2);
        static::assertEquals(['value1', 'value1 value2 value3', 'value2', 'value3'], $words1);
    }

    private static function getLongTextDescription(): string
    {
        return self::getLongTextPart1() . self::getLongTextPart2();
    }

    private static function getLongTextPart1(): string
    {
        return 'This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long descripti';
    }

    private static function getLongTextPart2(): string
    {
        return 'on. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description.';
    }
}
