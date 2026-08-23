<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Contena\Core\Content\Media\File\FileNameProvider;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Contena\Core\Framework\Plugin\KernelPluginCollection;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Kernel;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeDefinition;
use Contena\Frontend\Theme\ThemeEntity;
use Contena\Frontend\Theme\ThemeFilesystemResolver;
use Contena\Frontend\Theme\ThemeLifecycleService;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;
use Contena\Tests\Integration\Frontend\Theme\fixtures\ThemeWithFileAssociations\ThemeWithFileAssociations;
use Contena\Tests\Integration\Frontend\Theme\fixtures\ThemeWithLabels\ThemeWithLabels;

/**
 * @internal
 */
class ThemeLifecycleServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ThemeLifecycleService $themeLifecycleService;

    private Context $context;

    /**
     * @var EntityRepository<ThemeCollection>
     */
    private EntityRepository $themeRepository;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    /**
     * @var EntityRepository<MediaFolderCollection>
     */
    private EntityRepository $mediaFolderRepository;

    private Connection $connection;

    private ThemeFilesystemResolver $themeFilesystemResolver;

    private ThemeRuntimeConfigService&MockObject $themeRuntimeConfigService;

    protected function setUp(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            'ThemeWithFileAssociations' => new ThemeWithFileAssociations(),
            'ThemeWithLabels' => new ThemeWithLabels(),
        ]);
        $kernel->method('getBundle')->willReturnMap([
            ['ThemeWithFileAssociations', new ThemeWithFileAssociations()],
            ['ThemeWithLabels', new ThemeWithLabels()],
        ]);

        $pluginLoader = static::createStub(KernelPluginLoader::class);
        $pluginLoader->method('getPluginInstances')->willReturn(new KernelPluginCollection());
        $kernel->method('getPluginLoader')->willReturn($pluginLoader);

        $this->themeFilesystemResolver = new ThemeFilesystemResolver($kernel);

        $this->themeRepository = static::getContainer()->get('theme.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->mediaFolderRepository = static::getContainer()->get('media_folder.repository');
        $this->connection = static::getContainer()->get(Connection::class);

        $this->themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);

        $this->themeLifecycleService = new ThemeLifecycleService(
            static::getContainer()->get(FrontendPluginRegistry::class),
            $this->themeRepository,
            $this->mediaRepository,
            $this->mediaFolderRepository,
            static::getContainer()->get('theme_media.repository'),
            static::getContainer()->get(FileSaver::class),
            static::getContainer()->get(FileNameProvider::class),
            $this->themeFilesystemResolver,
            static::getContainer()->get('theme_child.repository'),
            $this->connection,
            static::getContainer()->get(FrontendPluginConfigurationFactory::class),
            $this->themeRuntimeConfigService,
        );

        $this->context = Context::createDefaultContext();
    }

    public function testRefreshThemesCorrectConfigurationCollection(): void
    {
        $pluginRegistry = static::getContainer()->get(FrontendPluginRegistry::class);
        $pluginConfigurationCollection = $pluginRegistry->getConfigurations();
        $bundle = $this->getThemeConfig();
        $themeConfigurations = new FrontendPluginConfigurationCollection([$bundle]);

        foreach ($themeConfigurations as $themeConfiguration) {
            $this->themeRuntimeConfigService->expects($this->once())
                ->method('refreshRuntimeConfig')
                ->with(static::anything(), $themeConfiguration, $this->context, false, $pluginConfigurationCollection);
        }

        $this->themeLifecycleService->refreshThemes($this->context, $themeConfigurations);
    }

    public function testMediaExtensionPointsToThemeDefinition(): void
    {
        $field = $this->mediaRepository->getDefinition()->getFields()->get('themeMedia');

        static::assertInstanceOf(ManyToManyAssociationField::class, $field);
        static::assertInstanceOf(ThemeDefinition::class, $field->getToManyReferenceDefinition());
    }

    public function testItRegistersANewThemeCorrectly(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertTrue($themeEntity->isActive());
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        static::assertCount(2, $themeEntity->getMedia());

        $themeDefaultFolderId = $this->getThemeMediaDefaultFolderId();
        foreach ($themeEntity->getMedia() as $media) {
            static::assertSame($themeDefaultFolderId, $media->getMediaFolderId());
        }
    }

    public function testThemeConfigInheritanceAddsParentTheme(): void
    {
        $parentBundle = $this->getThemeConfigWithLabels();
        $this->themeLifecycleService->refreshTheme($parentBundle, $this->context);

        $bundle = $this->getThemeConfig();
        $bundle->setConfigInheritance(['@' . $parentBundle->getTechnicalName()]);
        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $parentThemeEntity = $this->getTheme($parentBundle);
        $themeEntity = $this->getTheme($bundle);

        static::assertSame($parentThemeEntity->getId(), $themeEntity->getParentThemeId());
    }

    public function testThemeRefreshWithParentTheme(): void
    {
        $parentBundle = $this->getThemeConfigWithLabels();
        $this->themeLifecycleService->refreshTheme($parentBundle, $this->context);
        $bundle = $this->getThemeConfig();
        $bundle->setConfigInheritance(['@' . $parentBundle->getTechnicalName()]);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $parentThemeEntity = $this->getTheme($parentBundle);
        $themeEntity = $this->getTheme($bundle);

        static::assertSame($parentThemeEntity->getId(), $themeEntity->getParentThemeId());

        $bundle->setConfigInheritance([]);
        $this->themeLifecycleService->refreshTheme($parentBundle, $this->context);

        $themeEntity = $this->getTheme($bundle);
        static::assertSame($parentThemeEntity->getId(), $themeEntity->getParentThemeId());
    }

    public function testYouCanUpdateConfigToAddNewMedia(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);
        $this->addPinkLogoToTheme($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertTrue($themeEntity->isActive());
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        static::assertCount(3, $themeEntity->getMedia());
    }

    public function testItDoesNotRenameThemeMediaIfItExistsBeforeAndIsSame(): void
    {
        $bundle = $this->getThemeConfig();
        $this->addPinkLogoToTheme($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $logo = $this->getMedia('contena_logo');
        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        static::assertNotNull($themeEntity->getMedia()->get($logo->getId()));
    }

    public function testItRenamesThemeMediaIfItExistsBefore(): void
    {
        $bundle = $this->getThemeConfig();
        $this->addPinkLogoToThemeChanged($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);
        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        $renamedLogo = $this->getMedia('contena_logo_pink2');
        static::assertNotNull($themeEntity->getMedia()->get($renamedLogo->getId()));
    }

    public function testItIgnoresMediaFieldsWithoutValue(): void
    {
        $bundle = $this->getThemeConfig();
        $this->addPinkLogoToThemeWithoutValue($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        $this->hasNoMedia('contena_logo_pink2');
    }

    public function testItUploadsFilesIntoTheRootFolderIfThemeDefaultFolderDoesNotExist(): void
    {
        $bundle = $this->getThemeConfig();
        $themeMediaDefaultFolderId = $this->getThemeMediaDefaultFolderId();

        $this->connection->executeStatement('
            UPDATE `media`
            SET `media_folder_id` = null
            WHERE `media_folder_id` = :defaultThemeFolder
        ', ['defaultThemeFolder' => Uuid::fromHexToBytes($themeMediaDefaultFolderId)]);
        $this->mediaFolderRepository->delete([['id' => $themeMediaDefaultFolderId]], $this->context);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);
        static::assertTrue($themeEntity->isActive());
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        static::assertCount(2, $themeEntity->getMedia());

        foreach ($themeEntity->getMedia() as $media) {
            static::assertNull($media->getMediaFolderId());
        }
    }

    public function testItDoesNotOverridePreviewIfSetExplicitly(): void
    {
        $previewMediaId = Uuid::randomHex();
        $this->mediaRepository->create([['id' => $previewMediaId]], $this->context);

        $bundle = $this->getThemeConfig();
        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);
        $this->themeRepository->update([[
            'id' => $theme->getId(),
            'previewMediaId' => $previewMediaId,
        ]], $this->context);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);
        static::assertSame($previewMediaId, $theme->getPreviewMediaId());
    }

    public function testItRemovesAThemeCorrectly(): void
    {
        $bundle = $this->getThemeConfig();
        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        $ids = $themeEntity->getMedia()->getIds();

        $this->themeRuntimeConfigService->expects($this->once())
            ->method('deleteByTechnicalName')
            ->with($bundle->getTechnicalName());

        $this->themeLifecycleService->removeTheme($bundle->getTechnicalName(), $this->context);

        static::assertFalse($this->hasTheme($bundle));
        static::assertCount(0, $this->mediaRepository->searchIds(new Criteria($ids), Context::createDefaultContext())->getIds());
    }

    public function testItRemovesAChildThemeCorrectly(): void
    {
        $bundle = $this->getThemeConfig();
        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle, true);
        $childId = Uuid::randomHex();
        static::assertInstanceOf(ThemeCollection::class, $themeEntity->getDependentThemes());
        static::assertCount(0, $themeEntity->getDependentThemes());

        $this->themeRepository->clone($themeEntity->getId(), $this->context, $childId, new CloneBehavior([
            'technicalName' => null,
            'name' => 'Cloned theme',
            'parentThemeId' => $themeEntity->getId(),
        ]));

        $themeEntity = $this->getTheme($bundle, true);
        $themeMedia = $themeEntity->getMedia();
        static::assertInstanceOf(MediaCollection::class, $themeMedia);
        $ids = $themeMedia->getIds();
        static::assertInstanceOf(ThemeCollection::class, $themeEntity->getDependentThemes());
        static::assertCount(1, $themeEntity->getDependentThemes());

        $this->themeRuntimeConfigService->expects($this->once())
            ->method('deleteByTechnicalName')
            ->with($bundle->getTechnicalName());

        $this->themeLifecycleService->removeTheme($bundle->getTechnicalName(), $this->context);

        static::assertFalse($this->hasTheme($bundle));
        static::assertCount(0, $this->mediaRepository->searchIds(new Criteria($ids), Context::createDefaultContext())->getIds());
        static::assertCount(0, $this->themeRepository->search(new Criteria([$childId, $themeEntity->getId()]), $this->context)->getEntities());
    }

    private function getThemeConfig(): FrontendPluginConfiguration
    {
        $factory = static::getContainer()->get(FrontendPluginConfigurationFactory::class);

        return $factory->createFromBundle(new ThemeWithFileAssociations());
    }

    private function getThemeConfigWithLabels(): FrontendPluginConfiguration
    {
        $factory = static::getContainer()->get(FrontendPluginConfigurationFactory::class);

        return $factory->createFromBundle(new ThemeWithLabels());
    }

    private function getTheme(FrontendPluginConfiguration $bundle, bool $withChild = false): ThemeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $bundle->getTechnicalName()));
        $criteria->addAssociation('media');
        $criteria->addAssociation('translations.language.locale');

        if ($withChild) {
            $criteria->addAssociation('dependentThemes');
        }

        $theme = $this->themeRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(ThemeEntity::class, $theme);

        return $theme;
    }

    private function hasTheme(FrontendPluginConfiguration $bundle): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $bundle->getTechnicalName()));

        return $this->themeRepository->searchIds($criteria, $this->context)->getTotal() > 0;
    }

    private function getMedia(string $fileName): MediaEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));

        $media = $this->mediaRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(MediaEntity::class, $media);

        return $media;
    }

    private function hasNoMedia(string $fileName): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));

        static::assertNull($this->mediaRepository->search($criteria, $this->context)->getEntities()->first());
    }

    private function addPinkLogoToTheme(FrontendPluginConfiguration $bundle): void
    {
        $config = $bundle->getThemeConfig();
        static::assertIsArray($config);
        $config['fields']['contenaLogoPink'] = [
            'label' => ['en-GB' => 'contena_logo_pink', 'de-DE' => 'contena_logo_pink'],
            'type' => 'media',
            'value' => 'app/frontend/src/assets/image/contena_logo_pink.svg',
        ];

        $bundle->setThemeConfig($config);
    }

    private function addPinkLogoToThemeChanged(FrontendPluginConfiguration $bundle): void
    {
        $config = $bundle->getThemeConfig();
        static::assertIsArray($config);
        $config['fields']['contenaLogoPink'] = [
            'label' => ['en-GB' => 'contena_logo_pink', 'de-DE' => 'contena_logo_pink'],
            'type' => 'media',
            'value' => 'app/frontend/src/assets/image/contena_logo_pink2.svg',
        ];

        $bundle->setThemeConfig($config);
    }

    private function addPinkLogoToThemeWithoutValue(FrontendPluginConfiguration $bundle): void
    {
        $config = $bundle->getThemeConfig();
        static::assertIsArray($config);
        $config['fields']['contenaLogoPink'] = [
            'label' => ['en-GB' => 'contena_logo_pink', 'de-DE' => 'contena_logo_pink'],
            'type' => 'media',
        ];

        $bundle->setThemeConfig($config);
    }

    private function getThemeMediaDefaultFolderId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', 'theme'));
        $criteria->addAssociation('defaultFolder');
        $criteria->setLimit(1);
        $defaultFolder = $this->mediaFolderRepository->search($criteria, $this->context)->getEntities();

        if ($defaultFolder->count() !== 1 || $defaultFolder->first() === null) {
            throw new \RuntimeException('Default Theme folder does not exist.');
        }

        return $defaultFolder->first()->getId();
    }
}
