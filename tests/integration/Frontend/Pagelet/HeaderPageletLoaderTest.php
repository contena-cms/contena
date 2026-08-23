<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Pagelet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Frontend\Pagelet\Header\HeaderPageletLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class HeaderPageletLoaderTest extends TestCase
{
    use FrontendPageTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $languageRepository;

    protected function setUp(): void
    {
        $this->languageRepository = static::getContainer()->get('language.repository');
    }

    /**
     * @param list<array{name: string}> $languages
     * @param list<string> $expectedOrder
     */
    #[DataProvider('sortingTestDataProvider')]
    public function testLanguageSorting(array $languages, array $expectedOrder): void
    {
        $createdLanguages = [];
        foreach ($languages as $language) {
            $createdLanguages[] = [
                'name' => $language['name'],
                'id' => $this->createLanguage($language['name']),
            ];
        }

        $context = $this->createChannelContext($this->prepareChannelOverride($createdLanguages));

        $pageLanguages = $this->getPageLoader()->load(new Request(), $context)->getLanguages()->getElements();

        $i = 0;
        foreach ($pageLanguages as $pageLanguage) {
            static::assertSame($expectedOrder[$i], $pageLanguage->getName());
            ++$i;
        }
    }

    /**
     * Warning: Sorting is done after the position of the character inside the used collation.
     * Some characters like A and Ä share one position since Ä is being seen as A with decorations.
     * Adding a test case with e.g. Alang and Älang with an expected order will introduce flakynes.
     *
     * @return iterable<array{languages: list<array{name: string}>, expectedOrder: list<string>}>
     */
    public static function sortingTestDataProvider(): iterable
    {
        yield 'sorting test languages expected order' => [
            'languages' => [
                ['name' => 'Alang'],
                ['name' => 'Dlang'],
                ['name' => 'Xlang'],
                ['name' => 'Blang'],
            ],
            'expectedOrder' => ['Alang', 'Blang', 'Dlang', 'Xlang'],
        ];
        yield 'German fallback languages keep expected order' => [
            'languages' => [
                ['name' => 'Русский'],
                ['name' => 'हिन्दी'],
                ['name' => 'Glang'],
                ['name' => 'Ölang'],
                ['name' => 'Xlang'],
                ['name' => 'Elang'],
                ['name' => 'Flang'],
                ['name' => 'Plang'],
                ['name' => 'Qlang'],
                ['name' => 'Ylang'],
                ['name' => 'Mlang'],
                ['name' => 'Rlang'],
                ['name' => 'Jlang'],
                ['name' => '한국어'],
                ['name' => 'Slang'],
                ['name' => 'Ülang'],
                ['name' => 'Älang'],
                ['name' => 'Llang'],
            ],
            'expectedOrder' => [
                'Älang',
                'Elang',
                'Flang',
                'Glang',
                'Jlang',
                'Llang',
                'Mlang',
                'Ölang',
                'Plang',
                'Qlang',
                'Rlang',
                'Slang',
                'Ülang',
                'Xlang',
                'Ylang',
                'Русский',
                'हिन्दी',
                '한국어',
            ],
        ];
        yield 'mixed locale languages keep expected order' => [
            'languages' => [
                ['name' => 'Alang'],
                ['name' => 'Ablang'],
                ['name' => 'Axlang'],
                ['name' => 'Arlang'],
                ['name' => 'Aolang'],
                ['name' => 'Azlang'],
                ['name' => 'Anlang'],
                ['name' => 'Aqlang'],
                ['name' => 'Aülang'],
            ],
            'expectedOrder' => ['Ablang', 'Alang', 'Anlang', 'Aolang', 'Aqlang', 'Arlang', 'Aülang', 'Axlang', 'Azlang'],
        ];
    }

    protected function getPageLoader(): HeaderPageletLoader
    {
        return static::getContainer()->get(HeaderPageletLoader::class);
    }

    /**
     * @param list<array{name: string, id: string}> $languages
     *
     * @return array{languages: list<array{id: string}>, domains: list<array{url: string, languageId: string, snippetSetId: string|null}>, languageId: string}
     */
    private function prepareChannelOverride(array $languages): array
    {
        $languageIdArray = [];
        foreach ($languages as $language) {
            $languageIdArray[] = ['id' => $language['id']];
        }
        $domainArray = $this->getDomains($languages);

        return ['languages' => $languageIdArray, 'domains' => $domainArray, 'languageId' => $languages[0]['id']];
    }

    /**
     * @param list<array{name: string, id: string}> $languages
     *
     * @return list<array{url: string, languageId: string, snippetSetId: string|null}>
     */
    private function getDomains(array $languages): array
    {
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        $domains = [];

        foreach ($languages as $language) {
            $domains[] = [
                'url' => 'http://test.com/' . $language['id'],
                'languageId' => $language['id'],
                'snippetSetId' => $snippetSetId,
            ];
        }

        return $domains;
    }

    private function createLanguage(string $name): string
    {
        $localeId = Uuid::randomHex();
        $id = Uuid::randomHex();
        $this->languageRepository->upsert([
            [
                'id' => $id,
                'name' => $name,
                'locale' => [
                    'id' => $localeId,
                    'code' => 'de-DE-' . $localeId,
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
                'active' => true,
                'translationCodeId' => $localeId,
            ],
        ], Context::createDefaultContext());

        return $id;
    }
}
