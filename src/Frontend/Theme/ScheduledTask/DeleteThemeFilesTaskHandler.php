<?php

declare(strict_types=1);

namespace Contena\Frontend\Theme\ScheduledTask;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Frontend\Theme\UnusedThemeDirectoryDeleter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: DeleteThemeFilesTask::class)]
final class DeleteThemeFilesTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        private readonly UnusedThemeDirectoryDeleter $unusedThemeDirectoryDeleter,
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $this->unusedThemeDirectoryDeleter->deleteUnusedDirectories();
    }
}
