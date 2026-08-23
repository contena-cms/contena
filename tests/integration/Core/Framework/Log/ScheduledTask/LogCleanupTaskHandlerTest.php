<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Log\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\LogEntryCollection;
use Contena\Core\Framework\Log\ScheduledTask\LogCleanupTask;
use Contena\Core\Framework\Log\ScheduledTask\LogCleanupTaskHandler;
use Contena\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TenantTestBehaviour;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
class LogCleanupTaskHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use TenantTestBehaviour;

    private const string NOW = '2026-08-17 12:00:00 UTC';

    /**
     * @var EntityRepository<ScheduledTaskCollection>
     */
    private EntityRepository $scheduledTaskRepository;

    /**
     * @var EntityRepository<LogEntryCollection>
     */
    private EntityRepository $logEntryRepository;

    private SystemConfigService $systemConfigService;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM `log_entry`');

        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $this->scheduledTaskRepository = static::getContainer()->get('scheduled_task.repository');
        $this->logEntryRepository = static::getContainer()->get('log_entry.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testCleanupWithNoLimits(): void
    {
        $this->runWithOptions(-1, -1, [1, 2, 3]);
    }

    public function testCleanupWithEntryLimit(): void
    {
        $this->runWithOptions(-1, 2, [1, 2]);
    }

    public function testCleanupWithAgeLimit(): void
    {
        $year = 60 * 60 * 24 * 31 * 12;
        $this->runWithOptions((int) ($year * 1.5), -1, [1]);
    }

    public function testCleanupWithBothLimits(): void
    {
        $year = 60 * 60 * 24 * 31 * 12;
        $this->runWithOptions((int) ($year * 1.5), 2, [1]);
    }

    public function testRunHonorsTheFourContextWriteScopes(): void
    {
        $platformContext = Context::createDefaultContext();
        $tenantAContext = $this->createTenantContext($this->createTenant('Log cleanup tenant A'));
        $tenantBContext = $this->createTenantContext($this->createTenant('Log cleanup tenant B'));
        $year = 60 * 60 * 24 * 31 * 12;

        $this->systemConfigService->set('core.logging.entryLifetimeSeconds', (int) ($year * 1.5), context: $platformContext);
        $this->systemConfigService->set('core.logging.entryLimit', -1, context: $platformContext);

        $this->writeLogs($platformContext);
        $this->writeLogs($tenantAContext, 'tenantA');
        $this->writeLogs($tenantBContext, 'tenantB');

        $this->createHandler(Context::createGlobalContext())->run();

        static::assertSame(1, $this->countLogs($platformContext));
        static::assertSame(3, $this->countLogs($tenantAContext));
        static::assertSame(3, $this->countLogs($tenantBContext));

        $this->writeLogs($platformContext, 'platformDefault');
        $this->createHandler($platformContext)->run();

        static::assertSame(2, $this->countLogs($platformContext));
        static::assertSame(3, $this->countLogs($tenantAContext));
        static::assertSame(3, $this->countLogs($tenantBContext));

        $this->createHandler($tenantAContext)->run();

        static::assertSame(1, $this->countLogs($tenantAContext));
        static::assertSame(3, $this->countLogs($tenantBContext));

        $this->createHandler($tenantBContext)->run();

        static::assertSame(1, $this->countLogs($tenantBContext));
        static::assertSame(4, $this->countLogs(Context::createGlobalContext()));
    }

    public function testEntryLimitUsesTenantOverridesAndPlatformFallback(): void
    {
        $platformContext = Context::createDefaultContext();
        $tenantAContext = $this->createTenantContext($this->createTenant('Log limit tenant A'));
        $tenantBContext = $this->createTenantContext($this->createTenant('Log limit tenant B'));

        $this->systemConfigService->set('core.logging.entryLifetimeSeconds', -1, context: $platformContext);
        $this->systemConfigService->set('core.logging.entryLimit', 2, context: $platformContext);
        $this->systemConfigService->set('core.logging.entryLimit', 1, context: $tenantAContext);
        $this->systemConfigService->set('core.logging.entryLimit', -1, context: $tenantBContext);

        $this->writeLogs($platformContext);
        $this->writeLogs($tenantAContext, 'tenantA');
        $this->writeLogs($tenantBContext, 'tenantB');

        $this->createHandler()->run();

        static::assertSame(2, $this->countLogs($platformContext));
        static::assertSame(1, $this->countLogs($tenantAContext));
        static::assertSame(3, $this->countLogs($tenantBContext));
        static::assertSame(6, $this->countLogs(Context::createGlobalContext()));
    }

    public function testIsRegistered(): void
    {
        $registry = static::getContainer()->get(TaskRegistry::class);
        $registry->registerTasks();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', LogCleanupTask::getTaskName()));
        $task = $this->scheduledTaskRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($task);
        static::assertSame(LogCleanupTask::getDefaultInterval(), $task->getRunInterval());
    }

    /**
     * @param list<int> $logEntryNumbers
     */
    private function runWithOptions(int $age, int $maxEntries, array $logEntryNumbers): void
    {
        $this->systemConfigService->set('core.logging.entryLifetimeSeconds', $age);
        $this->systemConfigService->set('core.logging.entryLimit', $maxEntries);
        $this->writeLogs();

        $this->createHandler()->run();

        $results = $this->logEntryRepository->search(new Criteria(), $this->context);
        static::assertSame(\count($logEntryNumbers), $results->getTotal());

        $entries = $results->getEntities();
        $entriesJson = [];
        foreach ($entries as $entry) {
            $entriesJson[] = $entry->jsonSerialize();
        }

        $entryMessages = array_column($entriesJson, 'message');
        $entryContexts = array_column($entriesJson, 'context');
        static::assertContainsOnlyArray($entryContexts);
        $entryExtras = array_column($entriesJson, 'extra');
        static::assertContainsOnlyArray($entryExtras);
        foreach ($logEntryNumbers as $logEntryNumber) {
            static::assertContains('test' . $logEntryNumber, $entryMessages);
            static::assertContains(['contextTest' . $logEntryNumber => 'test' . $logEntryNumber], $entryContexts);
            static::assertContains(['extraTest' . $logEntryNumber => 'test' . $logEntryNumber], $entryExtras);
        }
    }

    private function createHandler(Context ...$contexts): LogCleanupTaskHandler
    {
        $contextProvider = static::getContainer()->get(TenantScopeContextProvider::class);
        if ($contexts !== []) {
            $contextProvider = static::createStub(TenantScopeContextProvider::class);
            $contextProvider->method('getContexts')->willReturnCallback(
                static function () use ($contexts): \Generator {
                    yield from $contexts;
                },
            );
        }

        return new LogCleanupTaskHandler(
            $this->scheduledTaskRepository,
            static::createStub(LoggerInterface::class),
            $this->systemConfigService,
            $this->connection,
            new MockClock(self::NOW),
            $contextProvider,
        );
    }

    private function countLogs(Context $context): int
    {
        return $this->logEntryRepository->search(new Criteria(), $context)->getTotal();
    }

    private function writeLogs(?Context $context = null, string $prefix = 'test'): void
    {
        $context ??= $this->context;

        $this->logEntryRepository->create(
            [
                [
                    'message' => $prefix . '1',
                    'level' => 12,
                    'channel' => 'test',
                    'context' => ['context' . ucfirst($prefix) . '1' => $prefix . '1'],
                    'extra' => ['extra' . ucfirst($prefix) . '1' => $prefix . '1'],
                    'createdAt' => new \DateTimeImmutable(self::NOW)->modify('-1 year')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
                [
                    'message' => $prefix . '2',
                    'level' => 42,
                    'channel' => 'test',
                    'context' => ['context' . ucfirst($prefix) . '2' => $prefix . '2'],
                    'extra' => ['extra' . ucfirst($prefix) . '2' => $prefix . '2'],
                    'createdAt' => new \DateTimeImmutable(self::NOW)->modify('-2 years')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
                [
                    'message' => $prefix . '3',
                    'level' => 1337,
                    'channel' => 'test',
                    'context' => ['context' . ucfirst($prefix) . '3' => $prefix . '3'],
                    'extra' => ['extra' . ucfirst($prefix) . '3' => $prefix . '3'],
                    'createdAt' => new \DateTimeImmutable(self::NOW)->modify('-3 years')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ],
            $context,
        );
    }
}
