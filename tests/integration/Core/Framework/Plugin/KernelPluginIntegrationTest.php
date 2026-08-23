<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Plugin;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Kernel\KernelFactory;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Migration\MigrationCollectionLoader;
use Contena\Core\Framework\Plugin\Composer\CommandExecutor;
use Contena\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Contena\Core\Framework\Plugin\PluginCollection;
use Contena\Core\Framework\Plugin\PluginLifecycleService;
use Contena\Core\Framework\Plugin\PluginService;
use Contena\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Contena\Core\Framework\Plugin\Util\AssetService;
use Contena\Core\Framework\Plugin\Util\VersionSanitizer;
use Contena\Core\Framework\Test\Plugin\PluginIntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Kernel;
use Contena\Core\System\CustomField\CustomFieldSetPersister;
use Contena\Core\System\SystemConfig\SystemConfigService;
use CtTestPlugin\CtTestPlugin;
use CtTestSkipRebuild\CtTestSkipRebuild;
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Group('slow')]
class KernelPluginIntegrationTest extends TestCase
{
    use PluginIntegrationTestBehaviour;

    private Kernel $kernel;

    protected function tearDown(): void
    {
        $serviceContainer = $this->kernel->getContainer()->get('test.service_container');
        static::assertInstanceOf(TestContainer::class, $serviceContainer);
        $serviceContainer->get('cache.object')->clear();
    }

    public function testWithDisabledPlugins(): void
    {
        $this->insertPlugin($this->getActivePlugin());

        $loader = new StaticKernelPluginLoader($this->classLoader);
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        static::assertEmpty($this->kernel->getPluginLoader()->getPluginInstances()->all());
    }

    public function testInactive(): void
    {
        $this->insertPlugin($this->getInstalledInactivePlugin());

        $loader = new DbalKernelPluginLoader(
            $this->classLoader,
            null,
            static::getContainer()->get(Connection::class)
        );
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $plugins = $this->kernel->getPluginLoader()->getPluginInstances();
        static::assertNotEmpty($plugins->all());

        $testPlugin = $plugins->get(CtTestPlugin::class);
        static::assertNotNull($testPlugin);

        static::assertFalse($testPlugin->isActive());
    }

    public function testActive(): void
    {
        $this->insertPlugin($this->getActivePlugin());

        static::getContainer()
            ->get(Connection::class)
            ->executeStatement('UPDATE plugin SET active = 1, installed_at = date(now())');

        $loader = new DbalKernelPluginLoader(
            $this->classLoader,
            null,
            static::getContainer()->get(Connection::class)
        );
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $testPlugin = $this->kernel->getPluginLoader()->getPluginInstances()->get(CtTestPlugin::class);
        static::assertNotNull($testPlugin);

        static::assertTrue($testPlugin->isActive());
    }

    public function testInactiveDefinitionsNotLoaded(): void
    {
        $this->insertPlugin($this->getInstalledInactivePlugin());

        $loader = new DbalKernelPluginLoader(
            $this->classLoader,
            null,
            static::getContainer()->get(Connection::class)
        );
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        static::assertFalse($this->kernel->getContainer()->has(CtTestPlugin::class));
    }

    public function testActiveAutoLoadedAndWired(): void
    {
        $this->insertPlugin($this->getActivePlugin());

        $loader = new DbalKernelPluginLoader(
            $this->classLoader,
            null,
            static::getContainer()->get(Connection::class)
        );
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $ctTestPlugin = $this->kernel->getContainer()->get(CtTestPlugin::class);
        static::assertInstanceOf(CtTestPlugin::class, $ctTestPlugin);

        // autowired
        static::assertInstanceOf(SystemConfigService::class, $ctTestPlugin->systemConfig);

        // manually set
        static::assertSame($this->kernel->getContainer()->get('tag.repository'), $ctTestPlugin->tagRepository);
    }

    public function testActivate(): void
    {
        $inactive = $this->getInstalledInactivePlugin();
        $this->insertPlugin($inactive);

        $loader = new DbalKernelPluginLoader(
            $this->classLoader,
            null,
            static::getContainer()->get(Connection::class)
        );
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $lifecycleService = $this->makePluginLifecycleService();
        $lifecycleService->activatePlugin($inactive, Context::createDefaultContext());

        $ctTestPlugin = $this->kernel->getPluginLoader()->getPluginInstances()->get($inactive->getBaseClass());
        static::assertInstanceOf(CtTestPlugin::class, $ctTestPlugin);

        // autowired
        static::assertInstanceOf(SystemConfigService::class, $ctTestPlugin->systemConfig);

        // manually set
        static::assertSame($this->kernel->getContainer()->get('tag.repository'), $ctTestPlugin->tagRepository);

        // the plugin services are still not loaded when the preActivate fires but in the postActivateContext event
        static::assertNull($ctTestPlugin->preActivateContext);
        static::assertNotNull($ctTestPlugin->postActivateContext);
        static::assertNull($ctTestPlugin->preDeactivateContext);
        static::assertNull($ctTestPlugin->postDeactivateContext);
    }

    public function testActivateWithoutRebuildWithSystemSource(): void
    {
        $inactive = $this->getInstalledInactivePluginRebuildDisabled();
        $this->insertPlugin($inactive);

        $loader = new DbalKernelPluginLoader(
            $this->classLoader,
            null,
            static::getContainer()->get(Connection::class)
        );
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $lifecycleService = $this->makePluginLifecycleService();
        $lifecycleService->activatePlugin($inactive, Context::createDefaultContext());

        $ctTestPlugin = $this->kernel->getPluginLoader()->getPluginInstances()->get($inactive->getBaseClass());
        static::assertInstanceOf(CtTestSkipRebuild::class, $ctTestPlugin);

        // not autowired
        static::assertNull($ctTestPlugin->systemConfig);

        // not set
        static::assertNull($ctTestPlugin->tagRepository);

        // the plugin services are still not loaded
        static::assertNull($ctTestPlugin->preActivateContext);
        static::assertNull($ctTestPlugin->postActivateContext);
        static::assertNull($ctTestPlugin->preDeactivateContext);
        static::assertNull($ctTestPlugin->postDeactivateContext);
    }

    public function testActivateWithoutRebuildWithNonSystemContext(): void
    {
        $inactive = $this->getInstalledInactivePluginRebuildDisabled();
        $this->insertPlugin($inactive);

        $loader = new DbalKernelPluginLoader(
            $this->classLoader,
            null,
            static::getContainer()->get(Connection::class)
        );
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $lifecycleService = $this->makePluginLifecycleService();
        $lifecycleService->activatePlugin($inactive, Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));

        $ctTestPlugin = $this->kernel->getPluginLoader()->getPluginInstances()->get($inactive->getBaseClass());
        static::assertInstanceOf(CtTestSkipRebuild::class, $ctTestPlugin);

        // autowired
        static::assertInstanceOf(SystemConfigService::class, $ctTestPlugin->systemConfig);

        // manually set
        static::assertSame($this->kernel->getContainer()->get('tag.repository'), $ctTestPlugin->tagRepository);

        // the plugin services are still not loaded when the preActivate fires but in the postActivateContext event
        static::assertNull($ctTestPlugin->preActivateContext);
        static::assertNotNull($ctTestPlugin->postActivateContext);
        static::assertNull($ctTestPlugin->preDeactivateContext);
        static::assertNull($ctTestPlugin->postDeactivateContext);
    }

    public function testDeactivate(): void
    {
        $active = $this->getActivePlugin();
        $this->insertPlugin($active);

        $loader = new DbalKernelPluginLoader(
            $this->classLoader,
            null,
            static::getContainer()->get(Connection::class)
        );
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $lifecycleService = $this->makePluginLifecycleService();

        $oldPluginInstance = $this->kernel->getPluginLoader()->getPluginInstances()->get($active->getBaseClass());
        static::assertInstanceOf(CtTestPlugin::class, $oldPluginInstance);

        $lifecycleService->deactivatePlugin($active, Context::createDefaultContext());

        $ctTestPlugin = $this->kernel->getPluginLoader()->getPluginInstances()->get($active->getBaseClass());
        static::assertInstanceOf(CtTestPlugin::class, $ctTestPlugin);

        // only the preDeactivate is called with the plugin still active
        static::assertNull($oldPluginInstance->preActivateContext);
        static::assertNull($oldPluginInstance->postActivateContext);
        static::assertNotNull($oldPluginInstance->preDeactivateContext);
        static::assertNull($oldPluginInstance->postDeactivateContext);

        // no plugin service should be loaded after deactivating it
        static::assertNull($ctTestPlugin->systemConfig);
        static::assertNull($ctTestPlugin->tagRepository);

        static::assertNull($ctTestPlugin->preActivateContext);
        static::assertNull($ctTestPlugin->postActivateContext);
        static::assertNull($ctTestPlugin->preDeactivateContext);
        static::assertNull($ctTestPlugin->postDeactivateContext);
    }

    public function testKernelParameters(): void
    {
        $plugin = $this->getInstalledInactivePlugin();
        $this->insertPlugin($plugin);

        $loader = new DbalKernelPluginLoader(
            $this->classLoader,
            null,
            static::getContainer()->get(Connection::class)
        );
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $expectedParameters = [
            'kernel.project_dir' => TEST_PROJECT_DIR,
            'kernel.plugin_dir' => TEST_PROJECT_DIR . '/custom/plugins',
        ];

        $actualParameters = [];
        foreach ($expectedParameters as $key => $_value) {
            $actualParameters[$key] = $this->kernel->getContainer()->getParameter($key);
        }

        static::assertSame($expectedParameters, $actualParameters);

        $lifecycleService = $this->makePluginLifecycleService();

        $lifecycleService->activatePlugin($plugin, Context::createDefaultContext());

        $newActualParameters = [];
        foreach ($expectedParameters as $key => $_value) {
            $newActualParameters[$key] = $this->kernel->getContainer()->getParameter($key);
        }

        $activePlugins = $this->kernel->getContainer()->getParameter('kernel.active_plugins');

        static::assertIsArray($activePlugins);
        static::assertArrayHasKey(CtTestPlugin::class, $activePlugins);

        static::assertArrayHasKey('name', $activePlugins[CtTestPlugin::class]);
        static::assertArrayHasKey('path', $activePlugins[CtTestPlugin::class]);
        static::assertArrayHasKey('class', $activePlugins[CtTestPlugin::class]);

        static::assertSame($expectedParameters, $newActualParameters);
    }

    public function testScheduledTaskIsRegisteredOnPluginStateChange(): void
    {
        $plugin = $this->getInstalledInactivePlugin();
        $this->insertPlugin($plugin);

        $loader = new DbalKernelPluginLoader(
            $this->classLoader,
            null,
            static::getContainer()->get(Connection::class)
        );
        $this->makeKernel($loader);
        $kernel = $this->kernel;
        $kernel->boot();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'ct_test.test_task'));

        $context = Context::createDefaultContext();

        $scheduledTasksRepo = $kernel->getContainer()->get('scheduled_task.repository');
        $result = $scheduledTasksRepo->search($criteria, $context)->getEntities()->first();
        static::assertNull($result);

        $pluginLifecycleManager = $this->makePluginLifecycleService();
        $pluginLifecycleManager->activatePlugin($plugin, $context);

        $scheduledTasksRepo = $kernel->getContainer()->get('scheduled_task.repository');
        $result = $scheduledTasksRepo->search($criteria, $context)->getEntities();
        static::assertNotNull($result);

        $pluginLifecycleManager->deactivatePlugin($plugin, $context);

        $scheduledTasksRepo = $kernel->getContainer()->get('scheduled_task.repository');
        $result = $scheduledTasksRepo->search($criteria, $context)->getEntities()->first();
        static::assertNull($result);
    }

    private function makePluginLifecycleService(): PluginLifecycleService
    {
        $kernel = $this->kernel;
        $container = $kernel->getContainer();

        $emptyPluginCollection = new PluginCollection();
        $pluginRepoMock = $this->createMock(EntityRepository::class);

        $pluginRepoMock
            ->method('search')
            ->willReturn(new EntitySearchResult(0, $emptyPluginCollection, null, new Criteria(), Context::createDefaultContext()));

        return new PluginLifecycleService(
            $pluginRepoMock,
            $container->get('event_dispatcher'),
            $kernel->getPluginLoader()->getPluginInstances(),
            $container,
            $this->createMock(MigrationCollectionLoader::class),
            $this->createMock(AssetService::class),
            $this->createMock(CommandExecutor::class),
            $this->createMock(RequirementsValidator::class),
            new ArrayAdapter(),
            $container->getParameter('kernel.contena_version'),
            $this->createMock(SystemConfigService::class),
            $this->createMock(PluginService::class),
            $this->createMock(VersionSanitizer::class),
            $this->createMock(DefinitionInstanceRegistry::class),
            new RequestStack(),
            $this->createMock(CustomFieldSetPersister::class),
            new NativeClock()
        );
    }

    private function makeKernel(KernelPluginLoader $loader): Kernel
    {
        $kernel = KernelFactory::create(
            'test',
            true,
            KernelLifecycleManager::getClassLoader(),
            $loader,
            static::getContainer()->get(Connection::class)
        );
        static::assertInstanceOf(Kernel::class, $kernel);
        $this->kernel = $kernel;

        return $this->kernel;
    }
}
