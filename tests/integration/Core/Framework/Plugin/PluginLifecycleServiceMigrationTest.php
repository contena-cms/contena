<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Plugin;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Migration\MigrationCollection;
use Contena\Core\Framework\Migration\MigrationCollectionLoader;
use Contena\Core\Framework\Migration\MigrationSource;
use Contena\Core\Framework\Plugin\Composer\CommandExecutor;
use Contena\Core\Framework\Plugin\KernelPluginCollection;
use Contena\Core\Framework\Plugin\PluginCollection;
use Contena\Core\Framework\Plugin\PluginEntity;
use Contena\Core\Framework\Plugin\PluginLifecycleService;
use Contena\Core\Framework\Plugin\PluginService;
use Contena\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Contena\Core\Framework\Plugin\Util\AssetService;
use Contena\Core\Framework\Plugin\Util\PluginFinder;
use Contena\Core\Framework\Plugin\Util\VersionSanitizer;
use Contena\Core\Framework\Test\Migration\MigrationTestBehaviour;
use Contena\Core\Framework\Test\Plugin\PluginTestsHelper;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Kernel;
use Contena\Core\System\CustomField\CustomFieldSetPersister;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Group('slow')]
class PluginLifecycleServiceMigrationTest extends TestCase
{
    use KernelTestBehaviour;
    use MigrationTestBehaviour;
    use PluginTestsHelper;

    private ContainerInterface $container;

    /**
     * @var EntityRepository<PluginCollection>
     */
    private EntityRepository $pluginRepo;

    private PluginService $pluginService;

    private Connection $connection;

    private PluginLifecycleService $pluginLifecycleService;

    private Context $context;

    private string $fixturePath;

    public static function tearDownAfterClass(): void
    {
        $connection = Kernel::getConnection();

        $connection->executeStatement('DELETE FROM migration WHERE `class` LIKE "CtManualMigrationTest%"');
        $connection->executeStatement('DELETE FROM plugin');

        KernelLifecycleManager::bootKernel();
    }

    protected function setUp(): void
    {
        // force kernel boot
        KernelLifecycleManager::bootKernel();

        $this->container = static::getContainer();
        $this->pluginRepo = $this->container->get('plugin.repository');
        $this->connection = $this->container->get(Connection::class);
        $this->pluginLifecycleService = $this->createPluginLifecycleService();
        $this->context = Context::createDefaultContext();

        $this->fixturePath = __DIR__ . '/../../../../../tests/integration/Core/Framework/Plugin/_fixtures/';

        $this->pluginService = $this->createPluginService(
            $this->fixturePath . 'plugins',
            $this->container->getParameter('kernel.project_dir'),
            $this->pluginRepo,
            $this->container->get('language.repository'),
            $this->container->get(PluginFinder::class)
        );

        $this->addTestPluginToKernel(
            $this->fixturePath . 'plugins/CtManualMigrationTestPlugin',
            'CtManualMigrationTestPlugin'
        );
        $this->requireMigrationFiles();

        $this->pluginService->refreshPlugins($this->context, new NullIO());
        $this->connection->executeStatement('DELETE FROM plugin WHERE `name` = "CtTest"');
    }

    /**
     * Exercises the full plugin lifecycle (install -> activate -> update -> deactivate -> uninstall) as
     * a single ordered scenario. This was previously a #[Depends] chain of separate tests threading the
     * MigrationCollection through return values; collapsed into one test so it no longer pins execution
     * order (the steps are inherently sequential and only meaningful together).
     */
    public function testPluginMigrationLifecycle(): void
    {
        static::assertSame(0, $this->connection->getTransactionNestingLevel());

        // install
        $migrationPlugin = $this->getMigrationTestPlugin();
        static::assertNull($migrationPlugin->getInstalledAt());

        $this->pluginLifecycleService->installPlugin($migrationPlugin, $this->context);
        $migrationCollection = $this->getMigrationCollection('CtManualMigrationTestPlugin');
        $this->assertMigrationState($migrationCollection, 4, 1);

        // activate
        $migrationPlugin = $this->getMigrationTestPlugin();
        $this->pluginLifecycleService->activatePlugin($migrationPlugin, $this->context);
        $this->assertMigrationState($migrationCollection, 4, 2);

        // update
        $migrationPlugin = $this->getMigrationTestPlugin();
        $this->pluginLifecycleService->updatePlugin($migrationPlugin, $this->context);
        $this->assertMigrationState($migrationCollection, 4, 3, 1);

        // deactivate
        $migrationPlugin = $this->getMigrationTestPlugin();
        $this->pluginLifecycleService->deactivatePlugin($migrationPlugin, $this->context);
        $this->assertMigrationState($migrationCollection, 4, 3, 1);

        // uninstall, keeping user data
        $migrationPlugin = $this->getMigrationTestPlugin();
        $this->pluginLifecycleService->uninstallPlugin($migrationPlugin, $this->context, true);
        $this->assertMigrationCount($migrationCollection, 4);
    }

    private function assertMigrationCount(MigrationCollection $migrationCollection, int $expectedCount): void
    {
        $connection = static::getContainer()->get(Connection::class);

        /** @var MigrationSource $migrationSource */
        $migrationSource = new \ReflectionProperty(MigrationCollection::class, 'migrationSource')->getValue($migrationCollection);

        $dbMigrations = $connection
            ->fetchAllAssociative(
                'SELECT * FROM `migration` WHERE `class` REGEXP :pattern ORDER BY `creation_timestamp`',
                ['pattern' => $migrationSource->getNamespacePattern()]
            );

        TestCase::assertCount($expectedCount, $dbMigrations);
    }

    private function createPluginLifecycleService(): PluginLifecycleService
    {
        return new PluginLifecycleService(
            $this->pluginRepo,
            $this->container->get('event_dispatcher'),
            $this->container->get(KernelPluginCollection::class),
            $this->container->get('service_container'),
            $this->container->get(MigrationCollectionLoader::class),
            $this->container->get(AssetService::class),
            $this->container->get(CommandExecutor::class),
            $this->container->get(RequirementsValidator::class),
            $this->container->get('cache.messenger.restart_workers_signal'),
            Kernel::CONTENA_FALLBACK_VERSION,
            $this->container->get(SystemConfigService::class),
            $this->container->get(PluginService::class),
            $this->container->get(VersionSanitizer::class),
            $this->container->get(DefinitionInstanceRegistry::class),
            new RequestStack(),
            $this->container->get(CustomFieldSetPersister::class),
            new NativeClock()
        );
    }

    private function getMigrationTestPlugin(): PluginEntity
    {
        return $this->pluginService
            ->getPluginByName('CtManualMigrationTestPlugin', $this->context);
    }

    private function requireMigrationFiles(): void
    {
        require_once $this->fixturePath . 'plugins/CtManualMigrationTestPlugin/src/Migration/Migration1.php';
        require_once $this->fixturePath . 'plugins/CtManualMigrationTestPlugin/src/Migration/Migration2.php';
        require_once $this->fixturePath . 'plugins/CtManualMigrationTestPlugin/src/Migration/Migration3.php';
        require_once $this->fixturePath . 'plugins/CtManualMigrationTestPlugin/src/Migration/Migration4.php';
    }
}
