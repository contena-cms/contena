<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Storefront\Theme\Snippet\ThemeSnippetFileWriter;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Contena\Storefront\Theme\ThemeCollection;
use Contena\Storefront\Theme\ThemeEntity;
use Contena\Storefront\Theme\ThemeLifecycleService;
use Contena\Storefront\Theme\ThemeRuntimeConfigService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ThemeLifecycleService::class)]
class ThemeLifecycleServiceTest extends TestCase
{
    public function testRefreshThemeUpdatesExistingThemeAndRefreshesRuntimeConfig(): void
    {
        $context = Context::createDefaultContext();
        $configuration = new StorefrontPluginConfiguration('ExampleTheme');
        $configuration->setName('Example');
        $configuration->setAuthor('Author');
        $configuration->setThemeJson(null);
        $configuration->setThemeConfig(null);

        $theme = new ThemeEntity();
        $theme->setId('theme-id');
        $theme->setUniqueIdentifier('theme-id');
        $theme->setTechnicalName('ExampleTheme');
        $theme->setThemeJson(null);

        $themeRepository = StaticEntityRepository::of(ThemeCollection::class, [new ThemeCollection([$theme])]);
        /** @var StaticEntityRepository<EntityCollection<Entity>> $themeChildRepository */
        $themeChildRepository = new StaticEntityRepository([[]]);

        $runtimeConfig = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfig->expects($this->once())->method('refreshRuntimeConfig')->with(
            'theme-id',
            $configuration,
            $context,
            false,
            static::isInstanceOf(StorefrontPluginConfigurationCollection::class)
        );
        $runtimeConfig->expects($this->once())->method('resetCaches');

        $snippetFileWriter = $this->createMock(ThemeSnippetFileWriter::class);
        $snippetFileWriter->expects($this->once())->method('write')->with($configuration);

        $service = $this->createService($themeRepository, $themeChildRepository, $runtimeConfig, $snippetFileWriter);
        $service->refreshTheme($configuration, $context);

        static::assertCount(2, $themeRepository->upserts);
        static::assertSame($themeRepository->upserts[0], $themeRepository->upserts[1]);
        static::assertSame([[]], $themeChildRepository->deletes);
    }

    public function testRefreshThemeNoLongerPersistsLegacyLabelTranslations(): void
    {
        $themeRepository = $this->createThemeRepositoryWithExistingTheme();
        /** @var StaticEntityRepository<EntityCollection<Entity>> $themeChildRepository */
        $themeChildRepository = new StaticEntityRepository([[]]);
        $service = $this->createService(
            $themeRepository,
            $themeChildRepository,
            static::createStub(ThemeRuntimeConfigService::class),
            static::createStub(ThemeSnippetFileWriter::class),
        );

        $service->refreshTheme($this->createConfigurationWithLegacyLabels(), Context::createDefaultContext());

        static::assertArrayNotHasKey('translations', $themeRepository->upserts[0][0]);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the legacy translation persistence in ThemeLifecycleService::refreshTheme
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testRefreshThemePersistsLegacyLabelTranslationsWithoutMajorFlag(): void
    {
        $themeRepository = $this->createThemeRepositoryWithExistingTheme();
        /** @var StaticEntityRepository<EntityCollection<Entity>> $themeChildRepository */
        $themeChildRepository = new StaticEntityRepository([[]]);
        $service = $this->createService(
            $themeRepository,
            $themeChildRepository,
            static::createStub(ThemeRuntimeConfigService::class),
            static::createStub(ThemeSnippetFileWriter::class),
        );

        $service->refreshTheme($this->createConfigurationWithLegacyLabels(), Context::createDefaultContext());

        static::assertSame(
            ['en-GB' => ['labels' => ['fields.sw-logo' => 'Logo'], 'helpTexts' => ['fields.sw-logo' => 'Header logo']]],
            $themeRepository->upserts[0][0]['translations'],
        );
    }

    /**
     * @return StaticEntityRepository<ThemeCollection>
     */
    private function createThemeRepositoryWithExistingTheme(): StaticEntityRepository
    {
        $theme = new ThemeEntity();
        $theme->setId('theme-id');
        $theme->setUniqueIdentifier('theme-id');
        $theme->setTechnicalName('ExampleTheme');
        $theme->setThemeJson(null);

        return StaticEntityRepository::of(ThemeCollection::class, [new ThemeCollection([$theme])]);
    }


}
