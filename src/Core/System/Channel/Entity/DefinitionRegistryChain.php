<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Entity;

use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Contena\Core\System\Channel\Exception\ChannelRepositoryNotFoundException;

/**
 * @internal
 */
class DefinitionRegistryChain
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $core,
        private readonly ChannelDefinitionInstanceRegistry $channel
    ) {
    }

    public function get(string $class): EntityDefinition
    {
        if ($this->channel->has($class)) {
            return $this->channel->get($class);
        }

        return $this->core->get($class);
    }

    /**
     * @return EntityRepository<covariant EntityCollection<covariant Entity>>|ChannelRepository<covariant EntityCollection<covariant Entity>>
     */
    public function getRepository(string $entity): EntityRepository|ChannelRepository
    {
        try {
            return $this->channel->getChannelRepository($entity);
        } catch (ChannelRepositoryNotFoundException) {
            return $this->core->getRepository($entity);
        }
    }

    public function getByEntityName(string $type): EntityDefinition
    {
        try {
            return $this->channel->getByEntityName($type);
        } catch (DefinitionNotFoundException) {
            return $this->core->getByEntityName($type);
        }
    }

    public function has(string $type): bool
    {
        return $this->channel->has($type) || $this->core->has($type);
    }
}
