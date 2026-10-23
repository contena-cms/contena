<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Contena\Core\Content\Media\File\FileNameProvider;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Frontend\Theme\Snippet\ThemeSnippetFileWriter;
use Contena\Frontend\Theme\FrontendPluginConfiguration\AbstractFrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeEntity;
use Contena\Frontend\Theme\ThemeFilesystemResolver;
use Contena\Frontend\Theme\ThemeLifecycleService;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;

/**
 * @internal
 */
#[CoversClass(ThemeLifecycleService::class)]
class ThemeLifecycleServiceTest extends TestCase
{
    public function testRefreshThemeUpdatesExistingThemeAndRefreshesRuntimeConfig(): void
    {
        $context = Context::createDefaultContext();
        $configuration = new FrontendPluginConfiguration('ExampleTheme');
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
            static::isInstanceOf(FrontendPluginConfigurationCollection::class)
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

    private function createConfigurationWithLegacyLabels(): FrontendPluginConfiguration
    {
        $configuration = new FrontendPluginConfiguration('ExampleTheme');
        $configuration->setName('Example');
        $configuration->setAuthor('Author');
        $configuration->setThemeJson(null);
        $configuration->setThemeConfig([
            'fields' => [
                'sw-logo' => [
                    'type' => 'media',
                    'label' => ['en-GB' => 'Logo'],
                    'helpText' => ['en-GB' => 'Header logo'],
                ],
            ],
        ]);

        return $configuration;
    }

    /**
     * @param EntityRepository<ThemeCollection> $themeRepository
     * @param EntityRepository<EntityCollection<Entity>> $themeChildRepository
     */
    private function createService(
        EntityRepository $themeRepository,
        EntityRepository $themeChildRepository,
        ThemeRuntimeConfigService $runtimeConfig,
        ThemeSnippetFileWriter $snippetFileWriter,
    ): ThemeLifecycleService {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier('language-id');
        $locale = new LocaleEntity();
        $locale->setCode('en-GB');
        $language->setTranslationCode($locale);

        $registry = static::createStub(FrontendPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn(new FrontendPluginConfigurationCollection());

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        return new ThemeLifecycleService(
            $registry,
            $themeRepository,
            new StaticEntityRepository([]),
            StaticEntityRepository::of(MediaFolderCollection::class, [[]]),
            new StaticEntityRepository([]),
            static::createStub(FileSaver::class),
            static::createStub(FileNameProvider::class),
            static::createStub(ThemeFilesystemResolver::class),
            new StaticEntityRepository([new LanguageCollection([$language])]),
            $themeChildRepository,
            $connection,
            static::createStub(AbstractFrontendPluginConfigurationFactory::class),
            $runtimeConfig,
            $snippetFileWriter,
        );
    }
}
