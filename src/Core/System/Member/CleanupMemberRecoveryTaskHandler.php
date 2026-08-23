<?php declare(strict_types=1);

namespace Contena\Core\System\Member;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: CleanupMemberRecoveryTask::class)]
final class CleanupMemberRecoveryTaskHandler extends ScheduledTaskHandler
{
    private const int BATCH_SIZE = 1000;

    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly TenantScopeContextProvider $tenantScopeContextProvider,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $threshold = $this->clock->now()->modify('-48 hour');

        foreach ($this->tenantScopeContextProvider->getContexts() as $context) {
            $this->cleanup($context, $threshold);
        }
    }

    private function cleanup(Context $context, \DateTimeInterface $threshold): void
    {
        $tenantId = $context->getTenantId();
        $tenantCondition = '`tenant_id` IS NULL';
        $parameters = [
            'timestamp' => $threshold->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'limit' => self::BATCH_SIZE,
        ];

        if ($tenantId !== null) {
            $tenantCondition = '`tenant_id` = :tenantId';
            $parameters['tenantId'] = Uuid::fromHexToBytes($tenantId);
        }

        do {
            $result = $this->connection->executeStatement(
                \sprintf('DELETE FROM `member_recovery` WHERE %s AND `created_at` <= :timestamp LIMIT :limit', $tenantCondition),
                $parameters,
                [
                    'limit' => ParameterType::INTEGER,
                ]
            );
        } while ($result >= self::BATCH_SIZE);
    }
}
