<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\ScheduledTask;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\ImportExport\Service\DeleteExpiredFilesService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: CleanupImportExportFileTask::class)]
final class CleanupImportExportFileTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly DeleteExpiredFilesService $deleteExpiredFilesService
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $this->deleteExpiredFilesService->deleteFiles(Context::createCLIContext());
    }
}
