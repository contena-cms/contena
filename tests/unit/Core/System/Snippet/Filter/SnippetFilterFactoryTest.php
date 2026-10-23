<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Snippet\Filter\AddedFilter;
use Contena\Core\System\Snippet\Filter\AuthorFilter;
use Contena\Core\System\Snippet\Filter\EditedFilter;
use Contena\Core\System\Snippet\Filter\EmptySnippetFilter;
use Contena\Core\System\Snippet\Filter\NamespaceFilter;
use Contena\Core\System\Snippet\Filter\SnippetFilterFactory;
use Contena\Core\System\Snippet\Filter\SnippetFilterInterface;
use Contena\Core\System\Snippet\Filter\TermFilter;
use Contena\Core\System\Snippet\Filter\TranslationKeyFilter;
use Contena\Core\System\Snippet\SnippetException;

/**
 * @internal
 */
#[CoversClass(SnippetFilterFactory::class)]
class SnippetFilterFactoryTest extends TestCase
{
    /**
     * @param class-string<SnippetFilterInterface>|null $expectedResult
     */
    #[DataProvider('dataProviderForTestGetFilter')]
    public function testGetFilter(string $filterName, ?string $expectedResult): void
    {
        $factory = new SnippetFilterFactory([
            new AuthorFilter(),
            new EditedFilter(),
            new EmptySnippetFilter(),
            new NamespaceFilter(),
            new TermFilter(),
            new TranslationKeyFilter(),
            new AddedFilter(),
        ]);

        if ($expectedResult === null) {
            $this->expectExceptionObject(SnippetException::filterNotFound($filterName, SnippetFilterFactory::class));
        }

        $result = $factory->getFilter($filterName);

        static::assertNotNull($expectedResult);
        static::assertInstanceOf($expectedResult, $result);
    }

    /**
     * @return list<array{0: string, 1: class-string<SnippetFilterInterface>|null}>
     */
    public static function dataProviderForTestGetFilter(): array
    {
        return [
            ['', null],
            ['foo', null],
            ['bar', null],
            ['author', AuthorFilter::class],
            ['edited', EditedFilter::class],
            ['empty', EmptySnippetFilter::class],
            ['namespace', NamespaceFilter::class],
            ['term', TermFilter::class],
            ['translationKey', TranslationKeyFilter::class],
            ['added', AddedFilter::class],
        ];
    }
}
