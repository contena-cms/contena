<?php declare(strict_types=1);

namespace Contena\Core\Framework\Log\ScheduledTask;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\Tenant\DataScopeContextProvider;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
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
        private readonly DataScopeContextProvider $dataScopeContextProvider,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        foreach ($this->dataScopeContextProvider->getContexts() as $context) {
            $this->cleanup($context);
        }
    }

    private function cleanup(Context $context): void
    {
        $entryLifetimeSeconds = $this->systemConfigService->getInt('core.logging.entryLifetimeSeconds', context: $context);
        $maxEntries = $this->systemConfigService->getInt('core.logging.entryLimit', context: $context);
        $parameters = ['dataScopeId' => Uuid::fromHexToBytes($context->getDataScopeId())];

        if ($entryLifetimeSeconds !== -1) {
            $deleteBefore = $this->clock->now()->modify(\sprintf('-%d seconds', $entryLifetimeSeconds))
                ->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $this->connection->executeStatement(
                'DELETE FROM `log_entry` WHERE `data_scope_id` = :dataScopeId AND `created_at` < :before',
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
                '`entry`.`data_scope_id` = :dataScopeId',
            );

            $this->connection->executeStatement(
                $sql,
                ['maxEntries' => $maxEntries, ...$parameters],
                ['maxEntries' => ParameterType::INTEGER],
            );
        }
    }
}
