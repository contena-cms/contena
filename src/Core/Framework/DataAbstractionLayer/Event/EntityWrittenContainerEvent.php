<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResultCollection;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\NestedEventCollection;

/**
 * @template IDStructure of string|array<string, string> = string
 */
class EntityWrittenContainerEvent extends NestedEvent
{
    protected bool $cloned = false;

    /**
     * @param NestedEventCollection<EntityWrittenEvent<IDStructure>> $events
     * @param array<mixed> $errors
     */
    public function __construct(
        protected Context $context,
        private readonly NestedEventCollection $events,
        private readonly array $errors
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return NestedEventCollection<EntityWrittenEvent<IDStructure>>
     */
    public function getEvents(): NestedEventCollection
    {
        return $this->events;
    }

    /**
     * @return EntityWrittenEvent<IDStructure>|null
     */
    public function getEventByEntityName(string $entityName): ?EntityWrittenEvent
    {
        foreach ($this->events as $event) {
            if (!$event instanceof EntityWrittenEvent) {
                continue;
            }

            if ($event->getEntityName() === $entityName) {
                return $event;
            }
        }

        return null;
    }

    /**
     * @return EntityWriteResultCollection<IDStructure>
     */
    public function getResults(string $entityName): EntityWriteResultCollection
    {
        /** @var list<EntityWriteResult<IDStructure>> $writeResults */
        $writeResults = [];

        foreach ($this->events as $event) {
            if (!$event instanceof EntityWrittenEvent || $event->getEntityName() !== $entityName) {
                continue;
            }

            foreach ($event->getWriteResults() as $writeResult) {
                $writeResults[] = $writeResult;
            }
        }

        /** @var EntityWriteResultCollection<IDStructure> $results */
        $results = new EntityWriteResultCollection($writeResults);

        return $results;
    }

    /**
     * @param array<string, list<EntityWriteResult>> $identifiers
     * @param array<mixed> $errors
     */
    public static function createWithWrittenEvents(array $identifiers, Context $context, array $errors, bool $cloned = false): self
    {
        $event = self::createEvents($identifiers, $context, $errors, EntityWrittenEvent::class);

        $event->setCloned($cloned);

        return $event;
    }

    /**
     * @param array<string, list<EntityWriteResult>> $identifiers
     * @param array<mixed> $errors
     */
    public static function createWithDeletedEvents(array $identifiers, Context $context, array $errors): self
    {
        return self::createEvents($identifiers, $context, $errors, EntityDeletedEvent::class);
    }

    /**
     * @internal used for debugging purposes only
     *
     * @return array<string, list<IDStructure>>
     */
    public function getList(): array
    {
        $list = [];

        foreach ($this->events as $event) {
            if ($event instanceof EntityWrittenEvent) {
                $list[$event->getName()] = $event->getIds();
            }
        }

        return $list;
    }

    /**
     * @param EntityWrittenEvent<IDStructure> ...$events
     */
    public function addEvent(NestedEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->events->add($event);
        }
    }

    /**
     * @return array<mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<IDStructure>
     */
    public function getPrimaryKeys(string $entity): array
    {
        return $this->getResults($entity)->getPrimaryKeys();
    }

    /**
     * @return list<IDStructure>
     */
    public function getDeletedPrimaryKeys(string $entity): array
    {
        return $this->getResults($entity)
            ->only(EntityWriteResult::OPERATION_DELETE)
            ->getPrimaryKeys();
    }

    /**
     * @param list<string> $ignoredFields
     *
     * @return list<IDStructure>
     */
    public function getPrimaryKeysWithPayloadIgnoringFields(string $entity, array $ignoredFields): array
    {
        return $this->findPrimaryKeys($entity, static function (EntityWriteResult $result) use ($ignoredFields) {
            if ($result->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                return true;
            }

            return array_diff(array_keys($result->getPayload()), $ignoredFields) !== [];
        });
    }

    /**
     * @param list<string> $properties
     *
     * @return list<IDStructure>
     */
    public function getPrimaryKeysWithPropertyChange(string $entity, array $properties): array
    {
        return $this->getResults($entity)
            ->withPayloadProperties(...$properties)
            ->getPrimaryKeys();
    }

    public function isCloned(): bool
    {
        return $this->cloned;
    }

    public function setCloned(bool $cloned): void
    {
        $this->cloned = $cloned;
    }

    /**
     * @param array<string, list<EntityWriteResult>> $identifiers
     * @param array<mixed> $errors
     */
    private static function createEvents(array $identifiers, Context $context, array $errors, string $event): self
    {
        $events = new NestedEventCollection();

        foreach ($identifiers as $data) {
            if (\count($data) === 0) {
                continue;
            }

            $first = current($data);

            $instance = new $event($first->getEntityName(), $data, $context, $errors);

            $events->add($instance);
        }

        return new self($context, $events, $errors);
    }

    /**
     * @return list<IDStructure>
     */
    private function findPrimaryKeys(string $entity, ?\Closure $closure = null): array
    {
        $ids = [];

        foreach ($this->events as $event) {
            if (!$event instanceof EntityWrittenEvent) {
                continue;
            }

            if ($event->getEntityName() !== $entity) {
                continue;
            }

            if (!$closure) {
                $ids = array_merge($ids, $event->getIds());

                continue;
            }

            foreach ($event->getWriteResults() as $result) {
                if ($closure($result)) {
                    $ids[] = $result->getPrimaryKey();
                }
            }
        }

        return $ids;
    }
}
