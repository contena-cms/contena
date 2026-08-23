<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context\Cleanup;

use Doctrine\DBAL\Connection;
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
#[AsMessageHandler(handles: CleanupChannelContextTask::class)]
final class CleanupChannelContextTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly Connection $connection,
        private readonly int $days,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $time = $this->clock->now()->modify(\sprintf('-%d day', $this->days));

        $this->connection->executeStatement(
            'DELETE FROM channel_api_context WHERE updated_at <= :timestamp',
            ['timestamp' => $time->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
        );
    }
}
