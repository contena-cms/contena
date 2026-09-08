<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ScheduledTask;

use Contena\Core\Framework\App\Event\SystemHeartbeatEvent;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[AsMessageHandler(handles: SystemHeartbeatTask::class)]
final class SystemHeartbeatHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->eventDispatcher->dispatch(new SystemHeartbeatEvent());
    }
}
