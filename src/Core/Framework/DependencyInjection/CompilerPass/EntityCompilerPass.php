<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\AttributeMappingDefinition;
use Contena\Core\Framework\DataAbstractionLayer\AttributeTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\FilteredBulkEntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\VersionManager;
use Contena\Core\Framework\DependencyInjection\DependencyInjectionException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

class EntityCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->collectDefinitions($container);
        $this->makeFieldSerializersPublic($container);
        $this->makeFieldResolversPublic($container);
        $this->makeFieldAccessorBuildersPublic($container);
    }

    private function collectDefinitions(ContainerBuilder $container): void
    {
        $entityNameMap = [];
        $repositoryNameMap = [];
        $services = $container->findTaggedServiceIds('contena.entity.definition');

        $ids = array_keys($services);

        foreach ($ids as $serviceId) {
            $service = $container->getDefinition($serviceId);

            $service->addMethodCall('compile', [
                new Reference(DefinitionInstanceRegistry::class),
            ]);
            $service->setPublic(true);

            /** @var string $class */
            $class = $service->getClass();

            if (!\is_subclass_of($class, EntityDefinition::class)) {
                throw DependencyInjectionException::taggedServiceHasWrongType($serviceId, 'contena.entity.definition', EntityDefinition::class);
            }

            if (\in_array($class, [AttributeEntityDefinition::class, AttributeTranslationDefinition::class, AttributeMappingDefinition::class], true)) {
                continue;
            }

            $instance = new $class();

            $entityNameMap[$instance->getEntityName()] = $serviceId;
            $entity = $instance->getEntityName();

            $repositoryId = $instance->getEntityName() . '.repository';

            try {
                $repository = $container->getDefinition($repositoryId);
            } catch (ServiceNotFoundException) {
                $repository = new Definition(
                    EntityRepository::class,
                    [
                        new Reference($serviceId),
                        new Reference(EntityReaderInterface::class),
                        new Reference(VersionManager::class),
                        new Reference(EntitySearcherInterface::class),
                        new Reference(EntityAggregatorInterface::class),
                        new Reference('event_dispatcher'),
                        new Reference(EntityLoadedEventFactory::class),
                    ]
                );
                $container->setDefinition($repositoryId, $repository);
            }
            $repository->setPublic(true);
            $container->registerAliasForArgument($repositoryId, EntityRepository::class);
            $container->registerAliasForArgument($repositoryId, EntityRepository::class);

            $repositoryNameMap[$entity] = $repositoryId;
        }

        $definitionRegistry = $container->getDefinition(DefinitionInstanceRegistry::class);
        $definitionRegistry->replaceArgument(1, $entityNameMap);
        $definitionRegistry->replaceArgument(2, $repositoryNameMap);

        $this->addExtensions($container, $entityNameMap);
    }

    /**
     * @param array<string, string> $entityNameMap
     */
    private function addExtensions(ContainerBuilder $container, array $entityNameMap): void
    {
        foreach ($container->findTaggedServiceIds('contena.entity.extension') as $id => $tags) {
            $extensionDefinition = $container->getDefinition($id);

            /** @var class-string $className */
            $className = $extensionDefinition->getClass() ?? $id;

            /** @var EntityExtension $extension */
            $extension = new \ReflectionClass($className)->newInstanceWithoutConstructor();
            $entityName = $extension->getEntityName();

            if (!isset($entityNameMap[$entityName]) || !$container->hasDefinition($entityNameMap[$entityName])) {
                throw DependencyInjectionException::definitionNotFound($entityName);
            }

            $container->getDefinition($entityNameMap[$entityName])
                ->addMethodCall('addExtension', [new Reference($id)]);
        }

        foreach ($container->findTaggedServiceIds('contena.bulk.entity.extension') as $id => $tags) {
            $extensionDefinition = $container->getDefinition($id);

            /** @var class-string $className */
            $className = $extensionDefinition->getClass() ?? $id;

            /** @var BulkEntityExtension $extension */
            $extension = new \ReflectionClass($className)->newInstanceWithoutConstructor();

            foreach (array_keys(iterator_to_array($extension->collect())) as $entityName) {
                if (!isset($entityNameMap[$entityName]) || !$container->hasDefinition($entityNameMap[$entityName])) {
                    throw DependencyInjectionException::definitionNotFound($entityName);
                }

                $filteredExtension = new Definition(FilteredBulkEntityExtension::class);
                $filteredExtension->addArgument($entityName);
                $filteredExtension->addArgument(new Reference($id));

                $container->getDefinition($entityNameMap[$entityName])
                    ->addMethodCall('addExtension', [$filteredExtension]);
            }
        }
    }

    private function makeFieldSerializersPublic(ContainerBuilder $container): void
    {
        $servicesIds = array_keys($container->findTaggedServiceIds('contena.field_serializer'));

        foreach ($servicesIds as $servicesId) {
            $container->getDefinition($servicesId)->setPublic(true);
        }
    }

    private function makeFieldResolversPublic(ContainerBuilder $container): void
    {
        $servicesIds = array_keys($container->findTaggedServiceIds('contena.field_resolver'));

        foreach ($servicesIds as $servicesId) {
            $container->getDefinition($servicesId)->setPublic(true);
        }
    }

    private function makeFieldAccessorBuildersPublic(ContainerBuilder $container): void
    {
        $servicesIds = array_keys($container->findTaggedServiceIds('contena.field_accessor_builder'));

        foreach ($servicesIds as $servicesId) {
            $container->getDefinition($servicesId)->setPublic(true);
        }
    }
}
