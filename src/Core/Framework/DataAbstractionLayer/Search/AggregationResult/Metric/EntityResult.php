<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;

/**
 * @final
 *
 * @template TEntityCollection of EntityCollection
 */
class EntityResult extends AggregationResult
{
    /**
     * @param TEntityCollection $entities
     */
    public function __construct(string $name, protected EntityCollection $entities)
    {
        parent::__construct($name);
    }

    /**
     * @return TEntityCollection
     */
    public function getEntities(): EntityCollection
    {
        return $this->entities;
    }

    public function add(Entity $entity): void
    {
        $this->entities->add($entity);
    }
}
