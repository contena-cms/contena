<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: DeleteUnusedGuestUserTask::class)]
final class DeleteUnusedGuestUserHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly DeleteUnusedGuestUserService $unusedGuestUserService
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->unusedGuestUserService->deleteUnusedUsers(Context::createCLIContext());
    }
}
