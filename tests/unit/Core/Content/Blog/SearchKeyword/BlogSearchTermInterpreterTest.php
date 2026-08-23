<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\SearchKeyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchTermInterpreter;
use Contena\Core\Content\Blog\SearchKeyword\KeywordLoader;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;

/**
 * @internal
 */
#[CoversClass(BlogSearchTermInterpreter::class)]
class BlogSearchTermInterpreterTest extends TestCase
{
    public function testReturnsEmptyPatternIfEmptyTerm(): void
    {
        $interpreter = new BlogSearchTermInterpreter(
            new Tokenizer(),
            static::createStub(LoggerInterface::class),
            new TokenFilter(static::createStub(SearchConfigLoader::class)),
            static::createStub(KeywordLoader::class),
            static::createStub(SearchConfigLoader::class),
        );

        $pattern = $interpreter->interpret('', Context::createDefaultContext());

        static::assertEmpty($pattern->getTerms());
    }

    public function testReturnsEmptyPatternIfTokensTooShort(): void
    {
        $interpreter = new BlogSearchTermInterpreter(
            static::createStub(Tokenizer::class),
            static::createStub(LoggerInterface::class),
            new TokenFilter(static::createStub(SearchConfigLoader::class)),
            static::createStub(KeywordLoader::class),
            static::createStub(SearchConfigLoader::class),
        );

        $pattern = $interpreter->interpret('a b c d', Context::createDefaultContext());

        static::assertEmpty($pattern->getTerms());
    }

    public function testTokenEncodingsStayIntact(): void
    {
        $term = 'foo-äöüß-مرحب-bar';
        $keywordLoader = static::createMock(KeywordLoader::class);
        $keywordLoader->expects($this->once())->method('fetch')
            ->with(static::callback(static function (array $tokenSlops): bool {
                $tokens = [...$tokenSlops['foo-äöüß-مرحب-bar']['reversed'], ...$tokenSlops['foo-äöüß-مرحب-bar']['normal']];
                $encodings = array_map(static fn (string $token): string|false => mb_detect_encoding($token, null, true), $tokens);

                static::assertNotContains(false, $encodings, 'At least one token is not properly encoded');

                return true;
            }));

        $configLoader = static::createStub(SearchConfigLoader::class);
        $configLoader->method('load')->willReturn([['min_search_length' => 3, 'excluded_terms' => []]]);

        $interpreter = new BlogSearchTermInterpreter(
            new Tokenizer(),
            static::createStub(LoggerInterface::class),
            new TokenFilter($configLoader),
            $keywordLoader,
            $configLoader,
        );

        $interpreter->interpret($term, Context::createDefaultContext());
    }

    public function testExactScoringMatches(): void
    {
        $term = 'Aerodynamic Aluminum Chambermaid Placemats';
        $keywordLoader = static::createMock(KeywordLoader::class);
        $keywordLoader->expects($this->once())->method('fetch')->willReturn([
            ['aerodynamic', '1', '0', '0', '0'],
            ['alumimagic', '0', '1', '0', '0'],
            ['aluminum', '0', '1', '0', '0'],
            ['chambermaid', '0', '0', '1', '0'],
            ['placemats', '0', '0', '0', '1'],
        ]);

        $configLoader = static::createStub(SearchConfigLoader::class);
        $configLoader->method('load')->willReturn([['min_search_length' => 3, 'excluded_terms' => []]]);
        $interpreter = new BlogSearchTermInterpreter(
            new Tokenizer(),
            static::createStub(LoggerInterface::class),
            new TokenFilter($configLoader),
            $keywordLoader,
            $configLoader,
        );

        $actual = $interpreter->interpret($term, Context::createDefaultContext());

        static::assertSame($term, $actual->getOriginal()->getTerm());
        static::assertSame(1.0, $actual->getOriginal()->getScore());

        $actualScoring = [];
        foreach ($actual->getTerms() as $searchTerm) {
            $actualScoring[$searchTerm->getTerm()] = $searchTerm->getScore();
        }

        static::assertSame([
            'aerodynamic' => 1.1,
            'aluminum' => 1.1,
            'chambermaid' => 1.1,
            'placemats' => 1.1,
            'alumimagic' => 0.1,
        ], $actualScoring);
    }

    public function testUsesConfiguredRelevantKeywordCount(): void
    {
        $keywordLoader = static::createMock(KeywordLoader::class);
        $keywordLoader->expects($this->once())->method('fetch')
            ->willReturn(array_map(static fn (int $index): array => [\sprintf('keyword-%02d', $index), '1'], range(1, 12)));

        $configLoader = static::createStub(SearchConfigLoader::class);
        $configLoader->method('load')->willReturn([['min_search_length' => 3, 'and_logic' => true, 'excluded_terms' => []]]);
        $interpreter = new BlogSearchTermInterpreter(
            new Tokenizer(),
            static::createStub(LoggerInterface::class),
            new TokenFilter($configLoader),
            $keywordLoader,
            $configLoader,
            10,
        );

        $pattern = $interpreter->interpret('search', Context::createDefaultContext());

        static::assertCount(10, $pattern->getTerms());
        static::assertSame(SearchPattern::BOOLEAN_CLAUSE_AND, $pattern->getBooleanClause());
    }
}
