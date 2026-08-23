<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Plugin;

use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Semver\Constraint\Constraint;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Migration\MigrationCollectionLoader;
use Contena\Core\Framework\Plugin\Composer\CommandExecutor;
use Contena\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Contena\Core\Framework\Plugin\Exception\PluginHasActiveDependantsException;
use Contena\Core\Framework\Plugin\KernelPluginCollection;
use Contena\Core\Framework\Plugin\PluginCollection;
use Contena\Core\Framework\Plugin\PluginEntity;
use Contena\Core\Framework\Plugin\PluginException;
use Contena\Core\Framework\Plugin\PluginLifecycleService;
use Contena\Core\Framework\Plugin\PluginService;
use Contena\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;
use Contena\Core\Framework\Plugin\Requirement\Exception\VersionMismatchException;
use Contena\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Contena\Core\Framework\Plugin\Util\AssetService;
use Contena\Core\Framework\Plugin\Util\PluginFinder;
use Contena\Core\Framework\Plugin\Util\VersionSanitizer;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\Framework\Test\Migration\MigrationTestBehaviour;
use Contena\Core\Framework\Test\Plugin\PluginTestsHelper;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Kernel;
use Contena\Core\System\CustomField\CustomFieldSetPersister;
use Contena\Core\System\SystemConfig\SystemConfigService;
use CtTestPlugin\Migration\Migration1536761533TestMigration;
use CtTestPlugin\CtTestPlugin;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Group('slow')]
class PluginLifecycleServiceTest extends TestCase
{
    use KernelTestBehaviour;
    use MigrationTestBehaviour;
    use PluginTestsHelper;

    private const string PLUGIN_NAME = 'CtTestPlugin';
    private const string DEPENDENT_PLUGIN_NAME = 'CtTestExtension';
    private const string NOT_SUPPORTED_VERSION_PLUGIN_NAME = 'CtTestNotSupportedVersion';

    private ContainerInterface $container;

    /**
     * @var EntityRepository<PluginCollection>
     */
    private EntityRepository $pluginRepo;

    private PluginService $pluginService;

    private KernelPluginCollection $pluginCollection;

    private Connection $connection;

    private PluginLifecycleService $pluginLifecycleService;

    private Context $context;

    private SystemConfigService $systemConfigService;

    private string $iso = 'sv-SE';

    private string $fixturePath;

    /**
     * @var array<array{string, callable}>
     */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        // force kernel boot
        KernelLifecycleManager::bootKernel();

        static::getContainer()
            ->get(Connection::class)
            ->beginTransaction();

        $this->fixturePath = __DIR__ . '/../../../../../tests/integration/Core/Framework/Plugin/_fixtures/';
        $this->container = static::getContainer();
        $this->pluginRepo = $this->container->get('plugin.repository');
        $this->pluginService = $this->createPluginService(
            $this->fixturePath . 'plugins',
            $this->container->getParameter('kernel.project_dir'),
            $this->pluginRepo,
            $this->container->get('language.repository'),
            $this->container->get(PluginFinder::class)
        );
        $this->pluginCollection = $this->container->get(KernelPluginCollection::class);
        $this->connection = $this->container->get(Connection::class);
        $this->systemConfigService = $this->container->get(SystemConfigService::class);
        $this->pluginLifecycleService = $this->createPluginLifecycleService($this->pluginService);

        require_once $this->fixturePath . 'plugins/CtTestPlugin/src/Migration/Migration1536761533TestMigration.php';

        $this->addTestPluginToKernel(
            $this->fixturePath . 'plugins/' . self::PLUGIN_NAME,
            self::PLUGIN_NAME
        );
        $this->addTestPluginToKernel(
            $this->fixturePath . 'plugins/CtTestWithoutConfig',
            'CtTestWithoutConfig'
        );

        $this->context = Context::createDefaultContext();
    }

    protected function tearDown(): void
    {
        static::getContainer()
            ->get(Connection::class)
            ->rollBack();

        if (isset($_SERVER['FAKE_MIGRATION_NAMESPACE'])) {
            unset($_SERVER['FAKE_MIGRATION_NAMESPACE']);
        }

        if (isset($_SERVER['TEST_KEEP_MIGRATIONS'])) {
            unset($_SERVER['TEST_KEEP_MIGRATIONS']);
        }

        $dispatcher = $this->container->get('event_dispatcher');

        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $dispatcher->removeListener($eventName, $listener);
        }

        $this->registeredListeners = [];
    }

    public function testInstallPlugin(): void
    {
        $this->installPluginTest($this->context);
    }

    public function testInstallPluginWithoutConfig(): void
    {
        $this->installPluginWithoutConfig($this->context);
    }

    public function testInstallPluginAlreadyInstalled(): void
    {
        $this->installPluginAlreadyInstalled($this->context);
    }

    public function testInstallPluginWithUpdate(): void
    {
        $this->installPluginWithUpdate($this->context);
    }

    public function testUninstallPlugin(): void
    {
        $this->uninstallPlugin($this->context);
    }

    public function testUninstallPluginThrowsException(): void
    {
        $this->uninstallPluginThrowsException($this->context);
    }

    public function testUninstallPluginWithoutConfig(): void
    {
        $this->uninstallPluginWithoutConfig($this->context);
    }

    public function testUpdatePlugin(): void
    {
        $this->updatePlugin($this->context);
    }

    public function testUpdatePluginThrowsIfPluginIsNotInstalled(): void
    {
        $this->updatePluginThrowsIfPluginIsNotInstalled($this->context);
    }

    public function testActivatePlugin(): void
    {
        $this->activatePlugin($this->context);
    }

    public function testActivatePluginThrowsException(): void
    {
        $this->activatePluginThrowsException($this->context);
    }

    public function testDeactivatePlugin(): void
    {
        $this->deactivatePlugin($this->context);
    }

    public function testDeactivatePluginNotInstalledThrowsException(): void
    {
        $this->deactivatePluginNotInstalledThrowsException($this->context);
    }

    public function testDeactivatePluginNotActivatedThrowsException(): void
    {
        $this->deactivatePluginNotActivatedThrowsException($this->context);
    }

    public function testRemoveMigrationsCannotRemoveContenaMigrations(): void
    {
        $this->removeMigrationsCannotRemoveContenaMigrations($this->context);
    }

    public function testInstallPluginWithNonStandardLanguage(): void
    {
        $this->installPluginTest($this->createNonStandardLanguageContext());
    }

    public function testInstallPluginWithoutConfigWithNonStandardLanguage(): void
    {
        $this->installPluginWithoutConfig($this->createNonStandardLanguageContext());
    }

    public function testUninstallPluginWithNonStandardLanguage(): void
    {
        $this->uninstallPlugin($this->createNonStandardLanguageContext());
    }

    public function testUninstallPluginThrowsExceptionWithNonStandardLanguage(): void
    {
        $this->uninstallPluginThrowsException($this->createNonStandardLanguageContext());
    }

    public function testActivatePluginWithNonStandardLanguage(): void
    {
        $this->activatePlugin($this->createNonStandardLanguageContext());
    }

    public function testActivatePluginThrowsExceptionWithNonStandardLanguage(): void
    {
        $this->activatePluginThrowsException($this->createNonStandardLanguageContext());
    }

    public function testDeactivatePluginWithNonStandardLanguage(): void
    {
        $this->deactivatePlugin($this->createNonStandardLanguageContext());
    }

    public function testDeactivatePluginNotInstalledThrowsExceptionWithNonStandardLanguage(): void
    {
        $this->deactivatePluginNotInstalledThrowsException($this->createNonStandardLanguageContext());
    }

    public function testDeactivatePluginNotActivatedThrowsExceptionWithNonStandardLanguage(): void
    {
        $this->deactivatePluginNotActivatedThrowsException($this->createNonStandardLanguageContext());
    }

    public function testRemoveMigrationsCannotRemoveContenaMigrationsWithNonStandardLanguage(): void
    {
        $this->removeMigrationsCannotRemoveContenaMigrations($this->createNonStandardLanguageContext());
    }

    public function testUpdateActivatedPluginWithException(): void
    {
        $this->updateActivatedPluginWithException($this->context);
    }

    public function testUpdateActivatedPluginWithExceptionWithNonStandardLanguage(): void
    {
        $this->updateActivatedPluginWithException($this->createNonStandardLanguageContext());
    }

    public function testUpdateActivatedPluginWithExceptionOnDeactivation(): void
    {
        $this->updateActivatedPluginWithExceptionOnDeactivation($this->context);
    }

    public function testUpdateDeactivatedPluginWithException(): void
    {
        $this->updateDeactivatedPluginWithException($this->context);
    }

    public function testAssetIsCalledOnlyWhenStateIsNotSet(): void
    {
        $assetService = $this->createMock(AssetService::class);
        $assetService
            ->expects($this->once())
            ->method('copyAssets');

        $service = new PluginLifecycleService(
            $this->pluginRepo,
            $this->container->get('event_dispatcher'),
            $this->pluginCollection,
            $this->container->get('service_container'),
            $this->container->get(MigrationCollectionLoader::class),
            $assetService,
            $this->container->get(CommandExecutor::class),
            $this->container->get(RequirementsValidator::class),
            $this->container->get('cache.messenger.restart_workers_signal'),
            Kernel::CONTENA_FALLBACK_VERSION,
            $this->systemConfigService,
            $this->container->get(PluginService::class),
            $this->container->get(VersionSanitizer::class),
            $this->container->get(DefinitionInstanceRegistry::class),
            new RequestStack(),
            $this->container->get(CustomFieldSetPersister::class),
            new NativeClock()
        );

        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);

        $this->createPlugin($this->pluginRepo, $context, CtTestPlugin::PLUGIN_OLD_VERSION);

        $plugin = $this->getPlugin($context);
        $service->installPlugin($plugin, $context);
        $service->activatePlugin($plugin, $context);
        $service->uninstallPlugin($plugin, $context);

        $context->removeState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);

        $service->installPlugin($plugin, $context);
        $service->activatePlugin($plugin, $context);
    }

    public function updateDeactivatedPluginWithException(Context $context): void
    {
        $installedAt = new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->createPlugin($this->pluginRepo, $context, CtTestPlugin::PLUGIN_OLD_VERSION, $installedAt);

        $plugin = $this->getPlugin($context);
        $context->addExtension(CtTestPlugin::THROW_ERROR_ON_UPDATE, new ArrayStruct());

        $this->expectExceptionObject(new \BadMethodCallException('Update throws an error'));
        $this->pluginLifecycleService->updatePlugin($plugin, $context);
    }

    public function updateActivatedPluginWithException(Context $context): void
    {
        $this->createPlugin($this->pluginRepo, $this->context, CtTestPlugin::PLUGIN_OLD_VERSION);
        $activatedPlugin = $this->installAndActivatePlugin($context);

        $context->addExtension(CtTestPlugin::THROW_ERROR_ON_UPDATE, new ArrayStruct());

        try {
            $this->pluginLifecycleService->updatePlugin($activatedPlugin, $context);
        } catch (\Throwable $exception) {
            static::assertInstanceOf(\BadMethodCallException::class, $exception);
            static::assertStringContainsString('Update throws an error', $exception->getMessage());
        }

        $plugin = $this->getTestPlugin($context);
        static::assertFalse($plugin->getActive());
    }

    public function updateActivatedPluginWithExceptionOnDeactivation(Context $context): void
    {
        $this->createPlugin($this->pluginRepo, $context, CtTestPlugin::PLUGIN_OLD_VERSION);
        $activatedPlugin = $this->installAndActivatePlugin($context);

        $context->addExtension(CtTestPlugin::THROW_ERROR_ON_UPDATE, new ArrayStruct());
        $context->addExtension(CtTestPlugin::THROW_ERROR_ON_DEACTIVATE, new ArrayStruct());

        try {
            $this->pluginLifecycleService->updatePlugin($activatedPlugin, $context);
        } catch (\Throwable $exception) {
            static::assertInstanceOf(\BadMethodCallException::class, $exception);
            static::assertStringContainsString('Update throws an error', $exception->getMessage());
        }

        $plugin = $this->getTestPlugin($context);
        static::assertFalse($plugin->getActive());
    }

    public function testDeactivatePluginWithDependencies(): void
    {
        $this->addTestPluginToKernel(
            $this->fixturePath . 'plugins/' . self::DEPENDENT_PLUGIN_NAME,
            self::DEPENDENT_PLUGIN_NAME
        );
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $basePlugin = $this->pluginService->getPluginByName(self::PLUGIN_NAME, $this->context);
        $this->pluginLifecycleService->installPlugin($basePlugin, $this->context);
        $this->pluginLifecycleService->activatePlugin($basePlugin, $this->context);

        $dependentPlugin = $this->pluginService->getPluginByName(self::DEPENDENT_PLUGIN_NAME, $this->context);
        $this->pluginLifecycleService->installPlugin($dependentPlugin, $this->context);
        $this->pluginLifecycleService->activatePlugin($dependentPlugin, $this->context);

        $expectedDependant = new PluginEntity();
        $expectedDependant->setName(self::DEPENDENT_PLUGIN_NAME);
        $this->expectExceptionObject(PluginException::hasActiveDependants(self::PLUGIN_NAME, [$expectedDependant]));

        try {
            $this->pluginLifecycleService->deactivatePlugin($basePlugin, $this->context);
        } catch (PluginHasActiveDependantsException $exception) {
            $params = $exception->getParameters();

            static::assertArrayHasKey('dependency', $params);
            static::assertArrayHasKey('dependants', $params);
            static::assertArrayHasKey('dependantNames', $params);

            $dependencyName = $params['dependency'];
            $dependants = $params['dependants'];
            $dependantNames = $params['dependantNames'];

            static::assertSame(self::PLUGIN_NAME, $dependencyName);
            static::assertCount(1, $dependants);
            static::assertSame(\sprintf('"%s"', self::DEPENDENT_PLUGIN_NAME), $dependantNames);

            $dependant = array_pop($dependants);

            static::assertInstanceOf(PluginEntity::class, $dependant);
            static::assertSame(self::DEPENDENT_PLUGIN_NAME, $dependant->getName());

            throw $exception;
        }
    }

    public function testActivateNotSupportedVersion(): void
    {
        $this->addTestPluginToKernel(
            $this->fixturePath . 'plugins/' . self::NOT_SUPPORTED_VERSION_PLUGIN_NAME,
            self::NOT_SUPPORTED_VERSION_PLUGIN_NAME
        );

        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $pluginEntity = $this->installNotSupportedPlugin(self::NOT_SUPPORTED_VERSION_PLUGIN_NAME);

        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        \assert(\is_string($projectDir));
        $rootComposer = Factory::create(new NullIO(), $projectDir . '/composer.json');
        $installedVersionString = new Constraint('==', $rootComposer->getPackage()->getVersion())->getPrettyString();

        $this->expectExceptionObject(new RequirementStackException(
            'activate',
            new VersionMismatchException('contena/core', '<6.0', $installedVersionString)
        ));
        $this->pluginLifecycleService->activatePlugin($pluginEntity, $this->context);
    }

    #[RunInSeparateProcess]
    public function testInstallationOfPluginWhichExecutesComposerCommandsWithPreviouslyInstalledPluginThatShipsVendorDirectory(): void
    {
        $this->addTestPluginToKernel(
            $this->fixturePath . 'plugins/CtTestShipsVendorDirectory',
            'CtTestShipsVendorDirectory'
        );
        $this->addTestPluginToKernel(
            $this->fixturePath . 'plugins/CtTestExecuteComposerCommands',
            'CtTestExecuteComposerCommands'
        );

        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $pluginWithVendor = $this->pluginService->getPluginByName('CtTestShipsVendorDirectory', $this->context);
        $this->pluginLifecycleService->installPlugin($pluginWithVendor, $this->context);

        $pluginWithExecuteComposer = $this->pluginService->getPluginByName('CtTestExecuteComposerCommands', $this->context);

        putenv('COMPOSER_DISABLE_NETWORK=1');
        $_ENV['COMPOSER_DISABLE_NETWORK'] = '1';
        $_SERVER['COMPOSER_DISABLE_NETWORK'] = '1';

        try {
            // Expected fail on executing the composer command, as the plugin is not in the default plugin directory and could therefore not be found
            $this->pluginLifecycleService->installPlugin($pluginWithExecuteComposer, $this->context);
            static::fail('Expected composer require to fail for a plugin outside the configured plugin directory.');
        } catch (\Throwable $e) {
            static::assertInstanceOf(PluginException::class, $e);
            static::assertStringContainsString('Could not execute "composer require"', $e->getMessage());
            static::assertStringContainsString('Network disabled, request canceled', $e->getMessage());
        } finally {
            putenv('COMPOSER_DISABLE_NETWORK');
            unset($_ENV['COMPOSER_DISABLE_NETWORK'], $_SERVER['COMPOSER_DISABLE_NETWORK']);
            \ComposerAutoloaderInitPluginTestShipsVendorDirectory::getLoader()->unregister();
        }
    }

    public function testPluginInstallFailureTriggersUninstall(): void
    {
        $plugin = $this->getPlugin($this->context);
        $expectedException = new \RuntimeException('Fail from post-install event');

        $listener = static function () use ($expectedException): void {
            throw $expectedException;
        };

        $this->addTestListener(PluginPostInstallEvent::class, $listener);

        try {
            $this->pluginLifecycleService->installPlugin($plugin, $this->context);
            static::fail('Expected exception was not thrown.');
        } catch (\Throwable $actualException) {
            static::assertSame($expectedException, $actualException);
            static::assertNull($plugin->getInstalledAt());
        }
    }

    private function installNotSupportedPlugin(string $name): PluginEntity
    {
        $pluginRepository = static::getContainer()->get('plugin.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $result = $pluginRepository->search($criteria, $this->context);
        $result = $result->getEntities()->first();
        static::assertInstanceOf(PluginEntity::class, $result);
        $date = new \DateTime();
        $result->setInstalledAt($date);
        $pluginRepository->update([[
            'id' => $result->getId(),
            'installedAt' => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]], $this->context);

        return $result;
    }

    private function installPluginTest(Context $context): void
    {
        $pluginInstalled = $this->installAndActivatePlugin($context);
        static::assertNotNull($pluginInstalled->getInstalledAt());

        $this->pluginLifecycleService->activatePlugin($pluginInstalled, $context);
        static::assertTrue($pluginInstalled->getActive());

        static::assertSame(1, $this->getMigrationTestKeyCount());

        static::assertSame(7, $this->systemConfigService->get('CtTestPlugin.config.intField'));
        static::assertNull($this->systemConfigService->get('CtTestPlugin.config.textFieldWithoutDefault'));
        static::assertSame('string', $this->systemConfigService->get('CtTestPlugin.config.textField'));
        static::assertNull($this->systemConfigService->get('CtTestPlugin.config.textFieldNull'));
        static::assertFalse($this->systemConfigService->get('CtTestPlugin.config.switchField'));
        static::assertSame(0.349831239840912348, $this->systemConfigService->get('CtTestPlugin.config.floatField'));
        static::assertNull($this->systemConfigService->get('CtTestPlugin.config.priceField'));
        static::assertSame('100', $this->systemConfigService->get('CtTestPlugin.config.numericTextField'));
        static::assertSame(['value1', 'value2'], $this->systemConfigService->get('CtTestPlugin.config.multiSelectField'));
    }

    private function installPluginWithoutConfig(Context $context): void
    {
        $this->pluginService->refreshPlugins($context, new NullIO());

        $plugin = $this->pluginService->getPluginByName('CtTestWithoutConfig', $context);

        $this->pluginLifecycleService->installPlugin($plugin, $context);

        $pluginInstalled = $this->pluginService->getPluginByName('CtTestWithoutConfig', $context);

        static::assertNotNull($pluginInstalled->getInstalledAt());
    }

    private function installPluginAlreadyInstalled(Context $context): void
    {
        $installedAt = new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->createPlugin($this->pluginRepo, $context, CtTestPlugin::PLUGIN_VERSION, $installedAt);

        $plugin = $this->getTestPlugin($context);

        $this->pluginLifecycleService->installPlugin($plugin, $context);

        $pluginInstalled = $this->getTestPlugin($context);

        static::assertNotNull($pluginInstalled->getInstalledAt());
        static::assertSame(
            $installedAt,
            $pluginInstalled->getInstalledAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        );
    }

    private function installPluginWithUpdate(Context $context): void
    {
        $this->createPlugin($this->pluginRepo, $context, CtTestPlugin::PLUGIN_OLD_VERSION);
        $pluginInstalled = $this->installPlugin($context);

        static::assertNotNull($pluginInstalled->getInstalledAt());
        static::assertNull($pluginInstalled->getUpgradedAt());
        static::assertSame(CtTestPlugin::PLUGIN_VERSION, $pluginInstalled->getVersion());
    }

    private function uninstallPlugin(Context $context): void
    {
        $pluginInstalled = $this->installPlugin($context);
        static::assertNotNull($pluginInstalled->getInstalledAt());

        $this->pluginLifecycleService->activatePlugin($pluginInstalled, $context);
        static::assertTrue($pluginInstalled->getActive());

        $this->pluginLifecycleService->uninstallPlugin($pluginInstalled, $context);

        $pluginUninstalled = $this->getTestPlugin($context);

        $pluginUninstalledConfigs = $this->systemConfigService->all();
        static::assertArrayNotHasKey('CtTest', $pluginUninstalledConfigs);
        static::assertNull($pluginUninstalled->getInstalledAt());
        static::assertFalse($pluginUninstalled->getActive());
    }

    private function uninstallPluginWithoutConfig(Context $context): void
    {
        $this->pluginService->refreshPlugins($context, new NullIO());

        $pluginInstalled = $this->pluginService->getPluginByName('CtTestWithoutConfig', $context);
        $this->pluginLifecycleService->installPlugin($pluginInstalled, $context);

        $this->pluginLifecycleService->activatePlugin($pluginInstalled, $context);
        static::assertTrue($pluginInstalled->getActive());

        $pluginConfigs = $this->systemConfigService->all();
        static::assertArrayNotHasKey('CtTest', $pluginConfigs);

        $this->pluginLifecycleService->uninstallPlugin($pluginInstalled, $context);

        $pluginUninstalled = $this->getTestPlugin($context);
        static::assertNull($pluginUninstalled->getInstalledAt());
        static::assertFalse($pluginUninstalled->getActive());
    }

    private function uninstallPluginThrowsException(Context $context): void
    {
        $plugin = $this->getPlugin($context);

        $this->expectExceptionObject(PluginException::notInstalled(self::PLUGIN_NAME));
        $this->pluginLifecycleService->uninstallPlugin($plugin, $context);
    }

    private function updatePlugin(Context $context): void
    {
        $installedAt = new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->createPlugin($this->pluginRepo, $context, CtTestPlugin::PLUGIN_OLD_VERSION, $installedAt);
        static::assertSame(0, $this->getMigrationTestKeyCount());

        $plugin = $this->getPlugin($context);
        $this->systemConfigService->set($plugin->getName() . '.config.intField', 5);
        $this->systemConfigService->delete($plugin->getName() . '.config.textField');

        $this->pluginLifecycleService->updatePlugin($plugin, $context);

        $pluginUpdated = $this->getTestPlugin($context);

        static::assertNotNull($pluginUpdated->getUpgradedAt());
        static::assertSame(CtTestPlugin::PLUGIN_VERSION, $pluginUpdated->getVersion());

        // modified config will not be changed, missing config should be reset to default
        $settings = $this->systemConfigService->getDomain($plugin->getName() . '.config');
        static::assertSame(5, $settings[$plugin->getName() . '.config.intField']);
        static::assertSame('string', $settings[$plugin->getName() . '.config.textField']);

        static::assertSame(1, $this->getMigrationTestKeyCount());
    }

    private function updatePluginThrowsIfPluginIsNotInstalled(Context $context): void
    {
        $this->createPlugin($this->pluginRepo, $context, CtTestPlugin::PLUGIN_OLD_VERSION);
        static::assertSame(0, $this->getMigrationTestKeyCount());

        $plugin = $this->getPlugin($context);

        $this->expectExceptionObject(PluginException::notInstalled(self::PLUGIN_NAME));
        $this->pluginLifecycleService->updatePlugin($plugin, $context);
    }

    private function activatePlugin(Context $context): void
    {
        $this->installAndActivatePlugin($context);

        $filesystem = $this->container->get(Filesystem::class);
        $filesystem->remove(__DIR__ . '/public');
    }

    private function activatePluginThrowsException(Context $context): void
    {
        $plugin = $this->getPlugin($context);

        $this->expectExceptionObject(PluginException::notInstalled(self::PLUGIN_NAME));
        $this->pluginLifecycleService->activatePlugin($plugin, $context);
    }

    private function deactivatePlugin(Context $context): void
    {
        $pluginActivated = $this->installAndActivatePlugin($context);

        $this->pluginLifecycleService->deactivatePlugin($pluginActivated, $context);

        $pluginDeactivated = $this->getTestPlugin($context);

        static::assertFalse($pluginDeactivated->getActive());

        $filesystem = $this->container->get(Filesystem::class);
        $filesystem->remove(__DIR__ . '/public');
    }

    private function deactivatePluginNotInstalledThrowsException(Context $context): void
    {
        $plugin = $this->getPlugin($context);

        $this->expectExceptionObject(PluginException::notInstalled(self::PLUGIN_NAME));
        $this->pluginLifecycleService->deactivatePlugin($plugin, $context);
    }

    private function deactivatePluginNotActivatedThrowsException(Context $context): void
    {
        $pluginInstalled = $this->installPlugin($context);

        static::assertNotNull($pluginInstalled->getInstalledAt());

        $this->expectExceptionObject(PluginException::notActivated(self::PLUGIN_NAME));
        $this->pluginLifecycleService->deactivatePlugin($pluginInstalled, $context);
    }

    private function removeMigrationsCannotRemoveContenaMigrations(Context $context): void
    {
        $this->pluginService->refreshPlugins($context, new NullIO());

        $overAllCount = $this->getMigrationCount('');

        $ctTest = new CtTestPlugin(true, '', '');

        $_SERVER['FAKE_MIGRATION_NAMESPACE'] = 'Contena\\Core';

        $exception = null;

        try {
            $ctTest->removeMigrations();
        } catch (\Exception $e) {
            $exception = $e;
        }

        $newOverAllCount = $this->getMigrationCount('');

        static::assertSame($overAllCount, $newOverAllCount);

        static::assertNotNull($exception, 'Expected exception to be thrown');
    }

    private function addLanguage(string $iso): string
    {
        $id = Uuid::randomHex();

        $languageRepository = static::getContainer()->get('language.repository');
        $localeId = $this->getIsoId($iso);
        $languageRepository->create(
            [
                [
                    'id' => $id,
                    'name' => $iso,
                    'localeId' => $localeId,
                    'active' => true,
                    'translationCode' => [
                        'id' => $localeId,
                        'code' => $iso,
                        'name' => 'test name',
                        'territory' => 'test',
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        return $id;
    }

    private function getIsoId(string $iso): string
    {
        $result = $this->connection->executeQuery('SELECT LOWER(HEX(id)) FROM locale WHERE code = ?', [$iso]);

        return (string) $result->fetchOne();
    }

    private function getMigrationCount(string $namespacePrefix): int
    {
        $result = $this->connection->executeQuery(
            'SELECT COUNT(*) FROM migration WHERE class LIKE :class',
            ['class' => addcslashes($namespacePrefix, '\\_%') . '%']
        )
            ->fetchOne();

        return (int) $result;
    }

    private function createNonStandardLanguageContext(): Context
    {
        $id = $this->addLanguage($this->iso);

        return new Context(new SystemSource(), [$id]);
    }

    private function createPluginLifecycleService(PluginService $pluginService): PluginLifecycleService
    {
        return new PluginLifecycleService(
            $this->pluginRepo,
            $this->container->get('event_dispatcher'),
            $this->pluginCollection,
            $this->container->get('service_container'),
            $this->container->get(MigrationCollectionLoader::class),
            $this->container->get(AssetService::class),
            $this->container->get(CommandExecutor::class),
            $this->container->get(RequirementsValidator::class),
            $this->container->get('cache.messenger.restart_workers_signal'),
            Kernel::CONTENA_FALLBACK_VERSION,
            $this->systemConfigService,
            $pluginService,
            $this->container->get(VersionSanitizer::class),
            $this->container->get(DefinitionInstanceRegistry::class),
            new RequestStack(),
            $this->container->get(CustomFieldSetPersister::class),
            new NativeClock()
        );
    }

    private function getMigrationTestKeyCount(): int
    {
        $result = $this->connection->executeQuery(
            'SELECT configuration_value FROM system_config WHERE configuration_key = ?',
            [Migration1536761533TestMigration::TEST_SYSTEM_CONFIG_KEY]
        );

        return (int) $result->fetchOne();
    }

    private function installPlugin(Context $context): PluginEntity
    {
        $plugin = $this->getPlugin($context);

        $this->pluginLifecycleService->installPlugin($plugin, $context);

        return $this->getTestPlugin($context);
    }

    private function installAndActivatePlugin(Context $context): PluginEntity
    {
        $pluginInstalled = $this->installPlugin($context);
        static::assertNotNull($pluginInstalled->getInstalledAt());

        $this->pluginLifecycleService->activatePlugin($pluginInstalled, $context);
        $pluginActivated = $this->getTestPlugin($context);
        static::assertTrue($pluginActivated->getActive());

        return $pluginActivated;
    }

    private function getPlugin(Context $context): PluginEntity
    {
        $this->pluginService->refreshPlugins($context, new NullIO());

        return $this->getTestPlugin($context);
    }

    private function getTestPlugin(Context $context): PluginEntity
    {
        return $this->pluginService->getPluginByName(self::PLUGIN_NAME, $context);
    }

    private function addTestListener(string $eventName, callable $listener): void
    {
        $dispatcher = $this->container->get('event_dispatcher');

        $dispatcher->addListener($eventName, $listener);

        $this->registeredListeners[] = [$eventName, $listener];
    }
}
