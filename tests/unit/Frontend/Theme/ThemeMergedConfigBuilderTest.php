<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeEntity;
use Contena\Frontend\Theme\ThemeMergedConfigBuilder;
use Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeFixtures;

/**
 * @internal
 */
#[CoversClass(ThemeMergedConfigBuilder::class)]
class ThemeMergedConfigBuilderTest extends TestCase
{
    private FrontendPluginRegistry&Stub $frontendPluginRegistryMock;

    /**
     * @var EntityRepository<ThemeCollection>&Stub
     */
    private EntityRepository&Stub $themeRepositoryMock;

    private ThemeMergedConfigBuilder $mergedConfigBuilder;

    private Context $context;

    protected function setUp(): void
    {
        $this->frontendPluginRegistryMock = static::createStub(FrontendPluginRegistry::class);
        $this->themeRepositoryMock = static::createStub(EntityRepository::class);

        $this->context = Context::createDefaultContext();

        $this->mergedConfigBuilder = new ThemeMergedConfigBuilder(
            $this->frontendPluginRegistryMock,
            $this->themeRepositoryMock,
        );
    }

    public function testGetPlainThemeConfigurationNoTheme(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new ThemeCollection(
                    [
                        new ThemeEntity()->assign(
                            [
                                '_uniqueIdentifier' => 'no',
                            ]
                        ),
                    ]
                ),
                null,
                new Criteria(),
                $this->context
            )
        );

        $this->expectExceptionObject(ThemeException::couldNotFindThemeById($themeId));

        $this->mergedConfigBuilder->getPlainThemeConfiguration($themeId, $this->context);
    }

    /**
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedStructured
     */
    #[DataProviderExternal(ThemeFixtures::class, 'getThemeCollectionForThemeConfiguration')]
    public function testGetPlainThemeConfiguration(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedStructured = null,
    ): void {
        $this->mockThemeRepositorySearch($themeCollection);

        $frontendPlugin = new FrontendPluginConfiguration('Test');
        $frontendPlugin->setThemeConfig(ThemeFixtures::getThemeJsonConfig());

        $this->frontendPluginRegistryMock->method('getConfigurations')->willReturn(
            new FrontendPluginConfigurationCollection(
                [
                    $frontendPlugin,
                ]
            )
        );

        $config = $this->mergedConfigBuilder->getPlainThemeConfiguration($ids['themeId'], $this->context);

        static::assertArrayHasKey('fields', $config);
        static::assertArrayHasKey('currentFields', $config);
        static::assertArrayHasKey('baseThemeFields', $config);
        static::assertEquals($expected, $config);
    }

    /**
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedStructured
     */
    #[DataProviderExternal(ThemeFixtures::class, 'getThemeCollectionForThemeConfiguration')]
    public function testGetThemeConfigurationFieldStructure(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedStructured = null,
    ): void {
        $this->mockThemeRepositorySearch($themeCollection);

        $frontendPlugin = new FrontendPluginConfiguration('Test');
        $frontendPlugin->setThemeConfig(ThemeFixtures::getThemeJsonConfig());

        $this->frontendPluginRegistryMock->method('getConfigurations')->willReturn(
            new FrontendPluginConfigurationCollection(
                [
                    $frontendPlugin,
                ]
            )
        );

        $config = $this->mergedConfigBuilder->getThemeConfigurationFieldStructure($ids['themeId'], $this->context);

        static::assertArrayHasKey('tabs', $config);
        static::assertArrayHasKey('default', $config['tabs']);
        static::assertArrayHasKey('blocks', $config['tabs']['default']);
        static::assertEquals($expectedStructured, $config);
    }

    private function mockThemeRepositorySearch(ThemeCollection $themeCollection): void
    {
        // Set up the mock to handle both the main search and the parent theme search
        $this->themeRepositoryMock->method('search')->willReturnCallback(
            function (Criteria $criteria) use ($themeCollection) {
                // If the criteria has a filter for a specific ID, find that theme
                $filters = $criteria->getFilters();
                foreach ($filters as $filter) {
                    if ($filter instanceof EqualsFilter
                        && $filter->getField() === 'id') {
                        $searchId = (string) $filter->getValue();
                        $foundTheme = $themeCollection->get($searchId);

                        if ($foundTheme) {
                            return new EntitySearchResult(
                                1,
                                new ThemeCollection([$foundTheme]),
                                null,
                                $criteria,
                                $this->context
                            );
                        }
                    }
                }

                // Default: return the full collection for the main search
                return new EntitySearchResult(
                    $themeCollection->count(),
                    $themeCollection,
                    null,
                    $criteria,
                    $this->context
                );
            }
        );
    }
}
