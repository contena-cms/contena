<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Indexing;

use Contena\Core\Content\Flow\Events\FlowIndexerEvent;
use Contena\Core\Content\Flow\FlowCollection;
use Contena\Core\Content\Flow\FlowDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
class FlowIndexer extends EntityIndexer
{
    public const NAME = 'flow.indexer';

    /**
     * @internal
     *
     * @param EntityRepository<FlowCollection> $repository
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly FlowPayloadUpdater $payloadUpdater,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if ($ids === []) {
            return null;
        }

        return new FlowIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(FlowDefinition::ENTITY_NAME);

        if ($updates === []) {
            return null;
        }

        $this->handle(new FlowIndexingMessage(array_values($updates), null, $event->getContext()));

        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids)) {
            return;
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return;
        }

        $context = $message->getContext();
        if ($message->isFullIndexing && $context->hasGlobalTenantAccess()) {
            foreach ($this->payloadUpdater->updateAllScopes($ids) as $batch) {
                $this->eventDispatcher->dispatch(new FlowIndexerEvent($batch['ids'], $batch['context']));
            }

            return;
        }

        $updatedIds = array_keys($this->payloadUpdater->update($ids, $context));
        if ($updatedIds !== []) {
            $this->eventDispatcher->dispatch(new FlowIndexerEvent($updatedIds, $context));
        }
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }
}
