<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Entity;

use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;

/**
 * @internal
 */
#[Package('framework')]
class DefinitionRegistryChain
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $core,
        private readonly SalesChannelDefinitionInstanceRegistry $salesChannel
    ) {
    }

    public function get(string $class): EntityDefinition
    {
        if ($this->salesChannel->has($class)) {
            return $this->salesChannel->get($class);
        }

        return $this->core->get($class);
    }

    /**
     * @return EntityRepository<covariant EntityCollection<covariant Entity>>|SalesChannelRepository<covariant EntityCollection<covariant Entity>>
     */
    public function getRepository(string $entity): EntityRepository|SalesChannelRepository
    {
        try {
            return $this->salesChannel->getSalesChannelRepository($entity);
        } catch (SalesChannelRepositoryNotFoundException) {
            return $this->core->getRepository($entity);
        }
    }

    public function getByEntityName(string $type): EntityDefinition
    {
        try {
            return $this->salesChannel->getByEntityName($type);
        } catch (DefinitionNotFoundException) {
            return $this->core->getByEntityName($type);
        }
    }

    public function has(string $type): bool
    {
        return $this->salesChannel->has($type) || $this->core->has($type);
    }
}
