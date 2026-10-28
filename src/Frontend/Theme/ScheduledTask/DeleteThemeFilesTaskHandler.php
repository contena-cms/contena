<?php

declare(strict_types=1);

namespace Contena\Storefront\Theme\ScheduledTask;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Storefront\Theme\UnusedThemeDirectoryDeleter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('discovery')]
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
