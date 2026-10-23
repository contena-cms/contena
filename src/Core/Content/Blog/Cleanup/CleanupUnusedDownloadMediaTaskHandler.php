<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cleanup;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\Media\UnusedMediaPurger;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: CleanupUnusedDownloadMediaTask::class)]
final class CleanupUnusedDownloadMediaTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly UnusedMediaPurger $unusedMediaPurger
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $this->unusedMediaPurger->deleteNotUsedMedia(
            null,
            null,
            null,
            BlogDownloadDefinition::ENTITY_NAME
        );
    }
}
