<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Frontend;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\AbstractFrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FileCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeChannel;
use Contena\Frontend\Theme\ThemeChannelCollection;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeEntity;
use Contena\Frontend\Theme\ThemeLifecycleHandler;
use Contena\Frontend\Theme\ThemeLifecycleService;
use Contena\Frontend\Theme\ThemeService;
use Contena\Tests\Integration\Frontend\Theme\fixtures\InheritanceWithConfig\InheritanceWithConfig;
use Contena\Tests\Integration\Frontend\Theme\fixtures\PluginWithAdditionalBundles\PluginWithAdditionalBundles;
use Contena\Tests\Integration\Frontend\Theme\fixtures\SimplePlugin\SimplePlugin;
use Contena\Tests\Integration\Frontend\Theme\fixtures\SimplePluginWithoutCompilation\SimplePluginWithoutCompilation;
use Contena\Tests\Integration\Frontend\Theme\fixtures\SimpleTheme\SimpleTheme;

/**
 * @internal
 */
class ThemeLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MockObject&ThemeService $themeServiceMock;

    private MockObject&FrontendPluginRegistry $configurationRegistryMock;

    private ThemeLifecycleHandler $themeLifecycleHandler;

    private AbstractFrontendPluginConfigurationFactory $configFactory;

    protected function setUp(): void
    {
        $this->themeServiceMock = $this->createMock(ThemeService::class);

        $this->configurationRegistryMock = $this->createMock(FrontendPluginRegistry::class);

        $this->themeLifecycleHandler = new ThemeLifecycleHandler(
            static::getContainer()->get(ThemeLifecycleService::class),
            $this->themeServiceMock,
            static::getContainer()->get('theme.repository'),
            $this->configurationRegistryMock,
            static::getContainer()->get(Connection::class)
        );

        $this->configFactory = static::getContainer()->get(FrontendPluginConfigurationFactory::class);

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM `theme_channel`');
        $this->assignThemeToDefaultChannel();
    }

    public function testHandleThemeInstallOrUpdateWillRecompileThemeIfNecessary(): void
    {
        $installConfig = $this->configFactory->createFromBundle(new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'));

        $this->themeServiceMock->expects($this->once())
            ->method('compileTheme')
            ->with(
                TestDefaults::CHANNEL,
                static::isString(),
                static::isInstanceOf(Context::class),
                static::callback(static fn (FrontendPluginConfigurationCollection $configs): bool => $configs->count() === 2)
            );

        $configs = new FrontendPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Frontend()),
            $installConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($installConfig, $configs, Context::createDefaultContext());
    }

    public function testHandleThemeInstallOrUpdateWillRecompilePluginWithSubBundles(): void
    {
        $installConfig = $this->configFactory->createFromBundle(new PluginWithAdditionalBundles(true, __DIR__ . '/fixtures/PluginWithSubBundles'));

        $this->themeServiceMock->expects($this->once())
            ->method('compileTheme')
            ->with(
                TestDefaults::CHANNEL,
                static::isString(),
                static::isInstanceOf(Context::class),
                static::callback(static fn (FrontendPluginConfigurationCollection $configs): bool => $configs->count() === 2)
            );

        $configs = new FrontendPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Frontend()),
            $installConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($installConfig, $configs, Context::createDefaultContext());
    }

    public function testHandleThemeInstallOrUpdateWithInheritance(): void
    {
        $installConfig = $this->configFactory->createFromBundle(new InheritanceWithConfig());

        $configs = new FrontendPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Frontend()),
            $installConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($installConfig, $configs, Context::createDefaultContext());

        /** @var EntityRepository<ThemeCollection> $themeRepository */
        $themeRepository = static::getContainer()->get('theme.repository');
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $installConfig->getTechnicalName()));
        $criteria->addAssociation('parentThemes');

        $themeEntity = $themeRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(ThemeEntity::class, $themeEntity);
        static::assertSame($installConfig->getTechnicalName(), $themeEntity->getTechnicalName());
    }

    public function testHandleThemeInstallOrUpdateWillRecompileOnlyTouchedTheme(): void
    {
        $channelId = $this->createChannel();
        $themeId = $this->createTheme('SimpleTheme', $channelId);
        $installConfig = $this->configFactory->createFromBundle(new SimpleTheme());
        $installConfig->setStyleFiles(FileCollection::createFromArray(['onlyForFile']));

        $this->themeServiceMock->expects($this->once())
            ->method('compileThemeById')
            ->with(
                $themeId,
                static::isInstanceOf(Context::class),
                static::callback(static fn (FrontendPluginConfigurationCollection $configs): bool => $configs->count() === 2)
            );

        $configs = new FrontendPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Frontend()),
            $installConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($installConfig, $configs, Context::createDefaultContext());
    }

    public function testHandleThemeUninstallWillRecompileThemeIfNecessary(): void
    {
        $uninstalledConfig = $this->configFactory->createFromBundle(new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'));

        $this->themeServiceMock->expects($this->once())
            ->method('compileTheme')
            ->with(
                TestDefaults::CHANNEL,
                static::isString(),
                static::isInstanceOf(Context::class),
                static::callback(static fn (FrontendPluginConfigurationCollection $configs): bool => $configs->count() === 1 && (
                    (
                        $configs->first() instanceof FrontendPluginConfiguration
                        ? $configs->first()->getTechnicalName()
                        : ''
                    ) === 'Frontend'
                ))
            );

        $configs = new FrontendPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Frontend()),
            $uninstalledConfig,
        ]);

        $this->configurationRegistryMock->expects($this->once())
            ->method('getConfigurations')
            ->willReturn($configs);

        $this->themeLifecycleHandler->handleThemeUninstall($uninstalledConfig, Context::createDefaultContext());
    }

    public function testHandleThemeUninstallWillNotRecompileThemeIfNotNecessary(): void
    {
        $uninstalledConfig = $this->configFactory->createFromBundle(new SimplePluginWithoutCompilation());

        $this->themeServiceMock->expects($this->never())
            ->method('compileTheme');

        $configs = new FrontendPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Frontend()),
            $uninstalledConfig,
        ]);

        $this->configurationRegistryMock->expects($this->once())
            ->method('getConfigurations')
            ->willReturn($configs);

        $this->themeLifecycleHandler->handleThemeUninstall($uninstalledConfig, Context::createDefaultContext());
    }

    public function testHandleThemeUninstallWillThrowExceptionIfThemeIsStillInUse(): void
    {
        $uninstalledConfig = $this->configFactory->createFromBundle(new SimpleTheme());
        $uninstalledConfig->setStyleFiles(new FileCollection());
        $uninstalledConfig->setScriptFiles(new FileCollection());

        $configs = new FrontendPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Frontend()),
            $uninstalledConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($uninstalledConfig, $configs, Context::createDefaultContext());
        $this->assignThemeToDefaultChannel('SimpleTheme');

        $scCollection = new ThemeChannelCollection();
        $scCollection->add(new ThemeChannel(Uuid::randomHex(), Uuid::randomHex()));
        $this->themeServiceMock->expects($this->once())
            ->method('getThemeDependencyMapping')
            ->willReturn($scCollection);

        $placeholderChannelId = 'sc-id';
        $this->expectExceptionObject(ThemeException::themeAssignmentException(
            'Simple theme',
            ['Simple theme' => [$placeholderChannelId]],
            [],
            [$placeholderChannelId => 'Headless'],
        ));

        $this->themeLifecycleHandler->handleThemeUninstall($uninstalledConfig, Context::createDefaultContext());
    }

    private function assignThemeToDefaultChannel(?string $themeName = null): void
    {
        $themeRepository = static::getContainer()->get('theme.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        if ($themeName) {
            $criteria->addFilter(new EqualsFilter('technicalName', $themeName));
        }

        $themeId = $themeRepository->searchIds($criteria, $context)->firstId();

        $themeRepository->update([
            [
                'id' => $themeId,
                'channels' => [
                    [
                        'id' => TestDefaults::CHANNEL,
                    ],
                ],
            ],
        ], $context);
    }

    private function createTheme(string $name, string $channelId): string
    {
        $id = Uuid::randomHex();

        $repository = static::getContainer()->get('theme.repository');

        $repository->create([
            [
                'id' => $id,
                'technicalName' => $name,
                'name' => $name,
                'author' => 'test',
                'active' => true,
                'channels' => [
                    [
                        'id' => $channelId,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $id;
    }

    private function createChannel(): string
    {
        $channelRepository = static::getContainer()->get('channel.repository');

        $id = Uuid::randomHex();
        $payload = [[
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::CHANNEL_TYPE_WEB,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'active' => true,
            'countryId' => $this->getValidCountryId(),
            'memberGroupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'serviceCategoryId' => $this->getValidCategoryId(),
            'serviceCategoryVersionId' => Defaults::LIVE_VERSION,
            'countries' => [['id' => $this->getValidCountryId()]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'domains' => [[
                'url' => 'http://localhost/' . $id,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            ]],
            'name' => 'first channel',
        ]];

        $channelRepository->create($payload, Context::createDefaultContext());

        return $id;
    }
}
