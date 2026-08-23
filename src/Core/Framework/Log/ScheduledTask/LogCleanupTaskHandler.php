<?php declare(strict_types=1);

namespace Contena\Core\Framework\Log\ScheduledTask;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: LogCleanupTask::class)]
final class LogCleanupTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly TenantScopeContextProvider $tenantScopeContextProvider,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        foreach ($this->tenantScopeContextProvider->getContexts() as $context) {
            $this->cleanup($context);
        }
    }

    private function cleanup(Context $context): void
    {
        $entryLifetimeSeconds = $this->systemConfigService->getInt('core.logging.entryLifetimeSeconds', context: $context);
        $maxEntries = $this->systemConfigService->getInt('core.logging.entryLimit', context: $context);
        $tenantId = $context->getTenantId();
        $tenantCondition = '`tenant_id` IS NULL';
        $aliasedTenantCondition = '`entry`.`tenant_id` IS NULL';
        $parameters = [];
        if ($tenantId !== null) {
            $tenantCondition = '`tenant_id` = :tenantId';
            $aliasedTenantCondition = '`entry`.`tenant_id` = :tenantId';
            $parameters['tenantId'] = Uuid::fromHexToBytes($tenantId);
        }

        if ($entryLifetimeSeconds !== -1) {
            $deleteBefore = $this->clock->now()->modify(\sprintf('-%d seconds', $entryLifetimeSeconds))
                ->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $this->connection->executeStatement(
                \sprintf('DELETE FROM `log_entry` WHERE %s AND `created_at` < :before', $tenantCondition),
                ['before' => $deleteBefore, ...$parameters],
            );
        }

        if ($maxEntries !== -1) {
            $sql = \sprintf(
                'DELETE ld FROM `log_entry` ld INNER JOIN (
                        SELECT `id` FROM (
                            SELECT
                                `entry`.`id`,
                                ROW_NUMBER() OVER (ORDER BY `entry`.`created_at` DESC, `entry`.`id` DESC) AS `scope_position`
                            FROM `log_entry` AS `entry`
                            WHERE %s
                        ) ranked
                        WHERE ranked.`scope_position` > :maxEntries
                    ) expired ON expired.`id` = ld.`id`',
                $aliasedTenantCondition,
            );

            $this->connection->executeStatement(
                $sql,
                ['maxEntries' => $maxEntries, ...$parameters],
                ['maxEntries' => ParameterType::INTEGER],
            );
        }
    }
}
