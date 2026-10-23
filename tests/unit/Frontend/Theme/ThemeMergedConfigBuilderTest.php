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
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeEntity;
use Contena\Frontend\Theme\ThemeMergedConfigBuilder;
use Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeFixtures;
use Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeFixtures_6_7;

/**
 * @internal
 */
#[CoversClass(ThemeMergedConfigBuilder::class)]
class ThemeMergedConfigBuilderTest extends TestCase
{
    use EnvTestBehaviour;

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
                'theme',
                1,
                new ThemeCollection(
                    [
                        (new ThemeEntity())->assign(
                            [
                                '_uniqueIdentifier' => 'no',
                                'channels' => new ChannelCollection(),
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
     * @deprecated tag:v6.8.0 Will be removed, use testGetPlainThemeConfiguration instead
     *
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedStructured
     */
    #[DataProviderExternal(ThemeFixtures_6_7::class, 'getThemeCollectionForThemeConfiguration')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetPlainThemeConfigurationWithTranslations(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedStructured = null,
    ): void {
        $this->testGetPlainThemeConfiguration($ids, $themeCollection, $expected, $expectedStructured);
    }

    /**
     * @deprecated tag:v6.8.0 Will be removed together with legacy theme labels
     *
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedStructured
     */
    #[DataProviderExternal(ThemeFixtures_6_7::class, 'getThemeCollectionForThemeConfiguration')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetPlainThemeConfigurationDoesNotTriggerLegacyLabelDeprecations(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedStructured = null,
    ): void {
        // Feature deprecations are suppressed in tests by default, so disable that to catch self-deprecations.
        $this->setEnvVars(['TESTS_RUNNING' => false]);

        $this->mockThemeRepositorySearch($themeCollection);

        $frontendPlugin = new FrontendPluginConfiguration('Test');
        $frontendPlugin->setThemeConfig(ThemeFixtures::getThemeJsonConfig());

        $this->frontendPluginRegistryMock->method('getConfigurations')->willReturn(
            new FrontendPluginConfigurationCollection([$frontendPlugin])
        );

        $deprecations = [];
        set_error_handler(static function (int $errorNumber, string $message) use (&$deprecations): bool {
            if ($errorNumber !== \E_USER_DEPRECATED) {
                return false;
            }

            $deprecations[] = $message;

            return true;
        });

        try {
            $this->mergedConfigBuilder->getPlainThemeConfiguration($ids['themeId'], $this->context, true);
            $this->mergedConfigBuilder->getThemeConfigurationFieldStructure($ids['themeId'], $this->context, true);
        } finally {
            restore_error_handler();
        }

        static::assertSame([], array_filter(
            $deprecations,
            static fn (string $message): bool => str_contains($message, ThemeEntity::class)
        ));
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

        $config = $this->mergedConfigBuilder->getPlainThemeConfiguration($ids['themeId'], $this->context, true);

        static::assertArrayHasKey('fields', $config);
        static::assertArrayHasKey('currentFields', $config);
        static::assertArrayHasKey('baseThemeFields', $config);
        static::assertEquals($expected, $config);
    }

    /**
     * @deprecated tag:v6.8.0 Will be removed, use testGetThemeConfigurationFieldStructure instead
     *
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedStructured
     */
    #[DataProviderExternal(ThemeFixtures_6_7::class, 'getThemeCollectionForThemeConfiguration')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetThemeConfigurationFieldStructureWithTranslations(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedStructured = null,
    ): void {
        $this->testGetThemeConfigurationFieldStructure($ids, $themeCollection, $expected, $expectedStructured);
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

        $config = $this->mergedConfigBuilder->getThemeConfigurationFieldStructure($ids['themeId'], $this->context, true);

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
                                'theme',
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
                    'theme',
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
