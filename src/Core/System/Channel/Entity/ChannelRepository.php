<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Entity;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\RepositorySearchDetector;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\DalSearchInstrumentor;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Profiling\Profiler;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Event\ChannelProcessCriteriaEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 *
 * @template TEntityCollection of EntityCollection
 */
class ChannelRepository
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityDefinition $definition,
        private readonly EntityReaderInterface $reader,
        private readonly EntitySearcherInterface $searcher,
        private readonly EntityAggregatorInterface $aggregator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityLoadedEventFactory $eventFactory,
        // wired by ChannelEntityCompilerPass; null only for hand-built repositories (tests), which run uninstrumented
        private readonly ?DalSearchInstrumentor $dalSearchInstrumentor = null,
    ) {
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     *
     * @return EntitySearchResult<TEntityCollection>
     */
    public function search(Criteria $criteria, ChannelContext $channelContext): EntitySearchResult
    {
        $searchFn = fn (): EntitySearchResult => $this->profile($criteria, fn (): EntitySearchResult => $this->_search($criteria, $channelContext));

        return $this->dalSearchInstrumentor?->measure(
            DalSearchInstrumentor::OPERATION_SEARCH,
            $this->definition,
            $criteria,
            $searchFn,
        ) ?? $searchFn();
    }

    public function aggregate(Criteria $criteria, ChannelContext $channelContext): AggregationResultCollection
    {
        $aggregateFn = fn (): AggregationResultCollection => $this->profile($criteria, fn (): AggregationResultCollection => $this->_aggregate($criteria, $channelContext));

        return $this->dalSearchInstrumentor?->measure(
            DalSearchInstrumentor::OPERATION_AGGREGATE,
            $this->definition,
            $criteria,
            $aggregateFn,
        ) ?? $aggregateFn();
    }

    public function searchIds(Criteria $criteria, ChannelContext $channelContext): IdSearchResult
    {
        $searchIdsFn = fn (): IdSearchResult => $this->profile($criteria, fn (): IdSearchResult => $this->_searchIds($criteria, $channelContext));

        return $this->dalSearchInstrumentor?->measure(
            DalSearchInstrumentor::OPERATION_SEARCH_IDS,
            $this->definition,
            $criteria,
            $searchIdsFn,
        ) ?? $searchIdsFn();
    }

    /**
     * @throws InconsistentCriteriaIdsException
     *
     * @return EntitySearchResult<TEntityCollection>
     */
    private function _search(Criteria $criteria, ChannelContext $channelContext): EntitySearchResult
    {
        $criteria = clone $criteria;

        $this->processCriteria($criteria, $channelContext);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            // nested sub-operation: profiled (span) but not metered; keep in sync with EntityRepository
            $aggregations = $this->profile($criteria, fn (): AggregationResultCollection => $this->_aggregate($criteria, $channelContext));
        }
        if (!RepositorySearchDetector::isSearchRequired($this->definition, $criteria)) {
            $entities = $this->read($criteria, $channelContext);

            return new EntitySearchResult($entities->count(), $entities, $aggregations, $criteria, $channelContext->getContext());
        }

        // nested sub-operation: profiled (span) but not metered; keep in sync with EntityRepository
        $ids = $this->profile($criteria, fn (): IdSearchResult => $this->doSearch($criteria, $channelContext));

        if ($ids->getIds() === []) {
            /** @var TEntityCollection $collection */
            $collection = $this->definition->getCollectionClass();

            return new EntitySearchResult($ids->getTotal(), new $collection(), $aggregations, $criteria, $channelContext->getContext());
        }

        $readCriteria = $criteria->cloneForRead($ids->getIds());

        $entities = $this->read($readCriteria, $channelContext);

        $search = $ids->getData();

        if (!$criteria->hasState(Criteria::STATE_DISABLE_SEARCH_INFO)) {
            foreach ($entities as $element) {
                if (!\array_key_exists($element->getUniqueIdentifier(), $search)) {
                    continue;
                }

                $data = $search[$element->getUniqueIdentifier()];
                unset($data['id']);

                if ($data === []) {
                    continue;
                }

                $element->addExtension('search', new ArrayEntity($data));
            }
        }

        $result = new EntitySearchResult($ids->getTotal(), $entities, $aggregations, $criteria, $channelContext->getContext());
        $result->addState(...$ids->getStates());

        $event = new EntitySearchResultLoadedEvent($this->definition, $result);
        $this->eventDispatcher->dispatch($event, $event->getName());

        $event = new ChannelEntitySearchResultLoadedEvent($this->definition, $result, $channelContext);
        $this->eventDispatcher->dispatch($event, $event->getName());

        return $result;
    }

    private function _aggregate(Criteria $criteria, ChannelContext $channelContext): AggregationResultCollection
    {
        $criteria = clone $criteria;

        $this->processCriteria($criteria, $channelContext);

        $result = $this->aggregator->aggregate($this->definition, $criteria, $channelContext->getContext());

        $event = new EntityAggregationResultLoadedEvent($this->definition, $result, $channelContext->getContext());
        $this->eventDispatcher->dispatch($event, $event->getName());

        return $result;
    }

    private function _searchIds(Criteria $criteria, ChannelContext $channelContext): IdSearchResult
    {
        $criteria = clone $criteria;

        $this->processCriteria($criteria, $channelContext);

        return $this->doSearch($criteria, $channelContext);
    }

    /**
     * Wraps a read operation in a profiler span (title-gated), independent of metric emission, so nested
     * sub-operations of a search are visible in the profiler without emitting a duplicate metric sample.
     *
     * @template TReturn
     *
     * @param \Closure(): TReturn $fn
     *
     * @return TReturn
     */
    private function profile(Criteria $criteria, \Closure $fn): mixed
    {
        $title = $criteria->getTitle();

        return $title === null ? $fn() : Profiler::trace($title, $fn, 'saleschannel-repository');
    }

    /**
     * @return TEntityCollection
     */
    private function read(Criteria $criteria, ChannelContext $channelContext): EntityCollection
    {
        $criteria = clone $criteria;

        /** @var TEntityCollection $entities */
        // @phpstan-ignore varTag.type (phpstan can't detect that TEntityCollection is always an EntityCollection<Entity>)
        $entities = $this->reader->read($this->definition, $criteria, $channelContext->getContext());

        if ($criteria->getFields() === []) {
            $events = $this->eventFactory->createForChannel($entities->getElements(), $channelContext);
        } else {
            $events = $this->eventFactory->createPartialForChannel($entities->getElements(), $channelContext);
        }

        foreach ($events as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $entities;
    }

    private function doSearch(Criteria $criteria, ChannelContext $channelContext): IdSearchResult
    {
        $result = $this->searcher->search($this->definition, $criteria, $channelContext->getContext());

        $event = new ChannelEntityIdSearchResultLoadedEvent($this->definition, $result, $channelContext);
        $this->eventDispatcher->dispatch($event, $event->getName());

        return $result;
    }

    private function processCriteria(Criteria $topCriteria, ChannelContext $channelContext): void
    {
        if (!$this->definition instanceof ChannelDefinitionInterface) {
            return;
        }

        $queue = [
            ['definition' => $this->definition, 'criteria' => $topCriteria, 'path' => ''],
        ];

        $maxCount = 100;

        $processed = [];

        // process all associations breadth-first
        while ($queue !== [] && --$maxCount > 0) {
            $cur = array_shift($queue);

            $definition = $cur['definition'];
            $criteria = $cur['criteria'];
            $path = $cur['path'];
            $processedKey = $path . $definition::class;

            if (isset($processed[$processedKey])) {
                continue;
            }

            if ($definition instanceof ChannelDefinitionInterface) {
                $definition->processCriteria($criteria, $channelContext);

                $eventName = \sprintf('channel.%s.process.criteria', $definition->getEntityName());
                $event = new ChannelProcessCriteriaEvent($criteria, $channelContext);

                $this->eventDispatcher->dispatch($event, $eventName);
            }

            $processed[$processedKey] = true;

            foreach ($criteria->getAssociations() as $associationName => $associationCriteria) {
                // find definition
                $field = $definition->getField($associationName);
                if (!$field instanceof AssociationField) {
                    continue;
                }

                $referenceDefinition = $field->getReferenceDefinition();
                $queue[] = ['definition' => $referenceDefinition, 'criteria' => $associationCriteria, 'path' => $path . '.' . $associationName];

                if (!$field instanceof ManyToManyAssociationField) {
                    continue;
                }

                $referenceDefinition = $field->getToManyReferenceDefinition();
                $queue[] = ['definition' => $referenceDefinition, 'criteria' => $associationCriteria, 'path' => $path . '.' . $associationName];
            }
        }
    }
}
