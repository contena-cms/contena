<?php declare(strict_types=1);

namespace Contena\Core\Content\BlogStream\ScheduledTask;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogStreamMappingIndexingMessage;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogStreamUpdater;
use Contena\Core\Content\BlogStream\BlogStreamCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[AsMessageHandler(handles: UpdateBlogStreamMappingTask::class)]
final class UpdateBlogStreamMappingTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $repository
     * @param EntityRepository<BlogStreamCollection> $blogStreamRepository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly EntityRepository $blogStreamRepository,
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

        $streamIds = $this->blogStreamRepository->searchIds($criteria, $context)->getPrimaryKeyData();
        if ($streamIds === []) {
            return;
        }

        // Touch the streams so cache invalidation subscribers (e.g. stream HTTP cache tags) fire.
        // BlogStreamUpdater::update() skips re-indexing when no filter property changed, so the
        // mapping update has to be triggered explicitly below.
        $this->blogStreamRepository->update($streamIds, $context);

        foreach ($streamIds as $streamId) {
            $message = new BlogStreamMappingIndexingMessage($streamId['id']);
            $message->setIndexer(BlogStreamUpdater::INDEXER_NAME);
            $this->messageBus->dispatch($message);
        }
    }
}
