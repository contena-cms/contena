<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule\DataAbstractionLayer;

use Contena\Core\Content\Rule\Event\RuleIndexerEvent;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Content\Rule\RuleDefinition;
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
class RuleIndexer extends EntityIndexer
{
    final public const string PAYLOAD_UPDATER = 'rule.payload';
    final public const string AREA_UPDATER = 'rule.area';

    /**
     * @internal
     *
     * @param EntityRepository<RuleCollection> $repository
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly RulePayloadUpdater $payloadUpdater,
        private readonly RuleAreaUpdater $areaUpdater,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getName(): string
    {
        return 'rule.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);
        $ids = $iterator->fetch();

        return $ids === [] ? null : new RuleIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $ids = $event->getPrimaryKeys(RuleDefinition::ENTITY_NAME);
        if ($ids !== []) {
            $this->handle(new RuleIndexingMessage(array_values($ids), null, $event->getContext()));
        }

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
        if (!$message->allow(self::PAYLOAD_UPDATER)) {
            if ($message->allow(self::AREA_UPDATER)) {
                $this->areaUpdater->update($ids);
            }

            $this->eventDispatcher->dispatch(new RuleIndexerEvent($ids, $context, array_values($message->getSkip())));

            return;
        }

        if ($message->isFullIndexing && $context->hasGlobalTenantAccess()) {
            if ($message->allow(self::AREA_UPDATER)) {
                $this->areaUpdater->update($ids);
            }

            foreach ($this->payloadUpdater->updateAllScopes($ids) as $batch) {
                $this->eventDispatcher->dispatch(new RuleIndexerEvent($batch['ids'], $batch['context'], array_values($message->getSkip())));
            }

            return;
        }

        $updatedIds = array_keys($this->payloadUpdater->update($ids, $context));
        if ($message->allow(self::AREA_UPDATER)) {
            $this->areaUpdater->update($ids);
        }

        if ($updatedIds !== []) {
            $this->eventDispatcher->dispatch(new RuleIndexerEvent($updatedIds, $context, array_values($message->getSkip())));
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
