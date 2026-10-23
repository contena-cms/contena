<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream\ScheduledTask;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\DataAbstractionLayer\ContentStreamMappingIndexingMessage;
use Contena\Core\Content\Blog\DataAbstractionLayer\ContentStreamUpdater;
use Contena\Core\Content\ContentStream\ContentStreamCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[AsMessageHandler(handles: UpdateContentStreamMappingTask::class)]
final class UpdateContentStreamMappingTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $repository
     * @param EntityRepository<ContentStreamCollection> $contentStreamRepository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly EntityRepository $contentStreamRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('filters.type', 'until'),
            new EqualsFilter('filters.type', 'since'),
        ]));

        $streamIds = $this->contentStreamRepository->searchIds($criteria, $context)->getPrimaryKeyData();
        if ($streamIds === []) {
            return;
        }

        // Touch the streams so cache invalidation subscribers (e.g. stream HTTP cache tags) fire.
        // ContentStreamUpdater::update() skips re-indexing when no filter property changed, so the
        // mapping update has to be triggered explicitly below.
        $this->contentStreamRepository->update($streamIds, $context);

        foreach ($streamIds as $streamId) {
            $message = new ContentStreamMappingIndexingMessage($streamId['id']);
            $message->setIndexer(ContentStreamUpdater::INDEXER_NAME);
            $this->messageBus->dispatch($message);
        }
    }
}
