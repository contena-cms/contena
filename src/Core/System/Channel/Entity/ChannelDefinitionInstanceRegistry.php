<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Entity;

use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Exception\ChannelRepositoryNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ChannelDefinitionInstanceRegistry extends DefinitionInstanceRegistry
{
    /**
     * @internal
     *
     * @param array<string, string|class-string<EntityDefinition>> $definitionMap
     * @param array<string, string> $repositoryMap
     */
    public function __construct(
        private readonly string $prefix,
        ContainerInterface $container,
        array $definitionMap,
        array $repositoryMap
    ) {
        parent::__construct($container, $definitionMap, $repositoryMap);
    }

    /**
     * @throws ChannelRepositoryNotFoundException
     *
     * @return ChannelRepository<covariant EntityCollection<covariant Entity>>
     */
    public function getChannelRepository(string $entityName): ChannelRepository
    {
        $channelRepositoryClass = $this->getChannelRepositoryClassByEntityName($entityName);

        $channelRepository = $this->container->get($channelRepositoryClass);
        \assert($channelRepository instanceof ChannelRepository);

        return $channelRepository;
    }

    public function get(string $class): EntityDefinition
    {
        if (!str_starts_with($class, $this->prefix)) {
            $class = $this->prefix . $class;
        }

        return parent::get($class);
    }

    /**
     * @return array<EntityDefinition&ChannelDefinitionInterface>
     */
    public function getChannelDefinitions(): array
    {
        return array_filter($this->getDefinitions(), static fn ($definition): bool => $definition instanceof ChannelDefinitionInterface);
    }

    public function register(EntityDefinition $definition, ?string $serviceId = null): void
    {
        if (!$serviceId) {
            $serviceId = $this->prefix . $definition::class;
        }

        parent::register($definition, $serviceId);
    }

    /**
     * @throws ChannelRepositoryNotFoundException
     */
    private function getChannelRepositoryClassByEntityName(string $entityName): string
    {
        if (!isset($this->repositoryMap[$entityName])) {
            throw ChannelException::repositoryNotFound($entityName);
        }

        return $this->repositoryMap[$entityName];
    }
}
