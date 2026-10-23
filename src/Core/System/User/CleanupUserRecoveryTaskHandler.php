<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: CleanupUserRecoveryTask::class)]
final class CleanupUserRecoveryTaskHandler extends ScheduledTaskHandler
{
    private const BATCH_SIZE = 1000;

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
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $threshold = $this->clock->now()->modify('-48 hour');

        do {
            $result = $this->connection->executeStatement(
                'DELETE FROM user_recovery WHERE created_at <= :timestamp LIMIT :limit',
                [
                    'timestamp' => $threshold->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'limit' => self::BATCH_SIZE,
                ],
                [
                    'limit' => ParameterType::INTEGER,
                ]
            );
        } while ($result >= self::BATCH_SIZE);
    }
}
