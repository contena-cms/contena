<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResultCollection;
use Contena\Core\Framework\Event\GenericEvent;
use Contena\Core\Framework\Event\NestedEvent;

/**
 * @template IDStructure of string|array<string, string> = string
 */
class EntityWrittenEvent extends NestedEvent implements GenericEvent
{
    /**
     * @var list<IDStructure>|null
     */
    protected ?array $ids = null;

    /**
     * @var list<array<string, mixed>>|null
     */
    protected ?array $payloads = null;

    protected string $name;

    /**
     * @param list<EntityWriteResult<IDStructure>> $writeResults
     * @param array<mixed> $errors
     */
    public function __construct(
        protected string $entityName,
        protected array $writeResults,
        protected Context $context,
        protected array $errors = []
    ) {
        $this->name = $this->entityName . '.written';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->context;
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
    public function getIds(): array
    {
        if ($this->ids === null) {
            $this->ids = [];
            foreach ($this->writeResults as $entityWriteResult) {
                $this->ids[] = $entityWriteResult->getPrimaryKey();
            }
        }

        return $this->ids;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPayloads(): array
    {
        if ($this->payloads === null) {
            $this->payloads = [];
            foreach ($this->writeResults as $entityWriteResult) {
                $this->payloads[] = $entityWriteResult->getPayload();
            }
        }

        return $this->payloads;
    }

    /**
     * @return list<EntityWriteResult<IDStructure>>
     */
    public function getWriteResults(): array
    {
        return $this->writeResults;
    }

    /**
     * @return EntityWriteResultCollection<IDStructure>
     */
    public function getResults(): EntityWriteResultCollection
    {
        /** @var EntityWriteResultCollection<IDStructure> $results */
        $results = new EntityWriteResultCollection($this->writeResults);

        return $results;
    }
}
