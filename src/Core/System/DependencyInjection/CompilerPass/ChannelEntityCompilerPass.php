<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection\CompilerPass;

use Contena\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\AttributeMappingDefinition;
use Contena\Core\Framework\DataAbstractionLayer\AttributeTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\FilteredBulkEntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\DalSearchInstrumentor;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\DependencyInjection\DependencyInjectionException;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

class ChannelEntityCompilerPass implements CompilerPassInterface
{
    private const PREFIX = 'channel_definition.';

    public function process(ContainerBuilder $container): void
    {
        $this->collectDefinitions($container);
    }

    private function collectDefinitions(ContainerBuilder $container): void
    {
        $entityNameMap = [];
        $repositoryNameMap = [];

        $channelDefinitions = $this->formatData(
            $container->findTaggedServiceIds('contena.channel.entity.definition'),
            $container
        );

        $baseDefinitions = $this->formatData(
            $container->findTaggedServiceIds('contena.entity.definition'),
            $container
        );

        $sortedData = $this->sortData($channelDefinitions, $baseDefinitions);

        foreach ($sortedData as $entityName => $definitions) {
            // if extended -> set up
            if (isset($definitions['extended'])) {
                $serviceId = $definitions['extended'];
                $entityNameMap[$entityName] = $serviceId;

                if (isset($definitions['alias'])) {
                    $entityNameMap[$definitions['alias']] = $serviceId;
                }

                $this->setUpEntityDefinitionService($container, $serviceId);
                $container->setAlias(self::PREFIX . $serviceId, new Alias($serviceId, true));
            }

            // if both mask base with extended as base
            if (isset($definitions['extended'], $definitions['base'])) {
                $container->setAlias(self::PREFIX . $definitions['base'], new Alias($definitions['extended'], true));
            }

            // if base only clone definition
            if (!isset($definitions['extended']) && isset($definitions['base'])) {
                $service = $container->getDefinition($definitions['base']);

                $clone = clone $service;
                $clone->removeMethodCall('compile');
                $clone->clearTags();
                $container->setDefinition(self::PREFIX . $definitions['base'], $clone);
                $this->setUpEntityDefinitionService($container, self::PREFIX . $definitions['base']);

                $entityNameMap[$entityName] = $definitions['base'];

                if (isset($definitions['alias'])) {
                    $entityNameMap[$definitions['alias']] = $definitions['base'];
                }
            }
        }

        /** @var string $serviceId */
        foreach ($channelDefinitions as $serviceId => $entityNames) {
            $service = $container->getDefinition($serviceId);

            $repositoryId = 'channel.' . $entityNames['entityName'] . '.repository';

            try {
                $repository = $container->getDefinition($repositoryId);
                $repository->setPublic(true);
            } catch (ServiceNotFoundException) {
                $serviceClass = $service->getClass();
                \assert(\is_string($serviceClass));
                $repository = new Definition(
                    ChannelRepository::class,
                    [
                        new Reference($serviceClass),
                        new Reference(EntityReaderInterface::class),
                        new Reference(EntitySearcherInterface::class),
                        new Reference(EntityAggregatorInterface::class),
                        new Reference('event_dispatcher'),
                        new Reference(EntityLoadedEventFactory::class),
                        new Reference(DalSearchInstrumentor::class),
                    ]
                );
                $repository->setPublic(true);

                $container->setDefinition($repositoryId, $repository);

                if (isset($entityNames['fallBack'])) {
                    $container->setAlias('channel.' . $entityNames['fallBack'] . '.repository', new Alias($repositoryId, true));
                }
            }

            $repositoryNameMap[$entityNames['entityName']] = $repositoryId;

            if (isset($entityNames['fallBack'])) {
                $repositoryNameMap[$entityNames['fallBack']] = $repositoryId;
            }
        }

        $definitionRegistry = $container->getDefinition(ChannelDefinitionInstanceRegistry::class);
        $definitionRegistry->replaceArgument(0, self::PREFIX);
        $definitionRegistry->replaceArgument(2, $entityNameMap);
        $definitionRegistry->replaceArgument(3, $repositoryNameMap);

        $this->addExtensions($container, $baseDefinitions, $channelDefinitions);
    }

    /**
     * @param array<string, array<mixed>> $taggedServiceIds
     *
     * @return array<string, array{entityName: string, fallBack?: string}>
     */
    private function formatData(
        array $taggedServiceIds,
        ContainerBuilder $container
    ): array {
        $result = [];

        foreach ($taggedServiceIds as $serviceId => $tags) {
            $service = $container->getDefinition($serviceId);

            /** @var string $class */
            $class = $service->getClass();

            if (\in_array($class, [AttributeEntityDefinition::class, AttributeTranslationDefinition::class, AttributeMappingDefinition::class], true)) {
                if ($service->getArguments() === []) {
                    continue;
                }

                $instance = new $class($service->getArguments()[0]);
            } else {
                $instance = new $class();
            }

            /** @var EntityDefinition $instance */
            $entityName = $instance->getEntityName();
            $result[$serviceId]['entityName'] = $entityName;

            if (isset($tags[0]['entity'])) {
                $result[$serviceId]['fallBack'] = $tags[0]['entity'];
            }
        }

        return $result;
    }

    /**
     * @param array<string, array<string, string>> $channelDefinitions
     * @param array<string, array<string, string>> $baseDefinitions
     *
     * @return array<string, array<string, string>>
     */
    private function sortData(array $channelDefinitions, array $baseDefinitions): array
    {
        $sorted = [];

        foreach ($baseDefinitions as $serviceId => $entityNames) {
            $sorted[$entityNames['entityName']]['base'] = $serviceId;

            if (isset($entityNames['fallBack'])) {
                $sorted[$entityNames['entityName']]['alias'] = $entityNames['fallBack'];
            }
        }

        foreach ($channelDefinitions as $serviceId => $entityNames) {
            $sorted[$entityNames['entityName']]['extended'] = $serviceId;

            if (isset($entityNames['fallBack'])) {
                $sorted[$entityNames['entityName']]['alias'] = $entityNames['fallBack'];
            }
        }

        return $sorted;
    }

    private function setUpEntityDefinitionService(ContainerBuilder $container, string $serviceId): void
    {
        $service = $container->getDefinition($serviceId);
        $service->setPublic(true);
        $service->addMethodCall('compile', [
            new Reference(ChannelDefinitionInstanceRegistry::class),
        ]);
    }

    /**
     * @param array<string, array{entityName: string, fallBack?: string}> $baseEntityDefinitions
     * @param array<string, array{entityName: string, fallBack?: string}> $channelDefinitions
     */
    private function addExtensions(ContainerBuilder $container, array $baseEntityDefinitions, array $channelDefinitions): void
    {
        $entityNameMap = [];
        $channelNameMap = [];

        foreach ($baseEntityDefinitions as $definition => $attrs) {
            $entityNameMap[$attrs['entityName']] = $definition;
        }

        foreach ($channelDefinitions as $definition => $attrs) {
            $channelNameMap[$attrs['entityName']] = $definition;
        }

        foreach ($container->findTaggedServiceIds('contena.entity.extension') as $id => $tags) {
            $definition = $container->getDefinition($id);

            /** @var class-string $className */
            $className = $definition->getClass() ?? $id;

            /** @var EntityExtension $classObject */
            $classObject = new \ReflectionClass($className)->newInstanceWithoutConstructor();

            if (!\array_key_exists($classObject->getEntityName(), $entityNameMap)) {
                throw DependencyInjectionException::definitionNotFound($classObject->getEntityName());
            }

            if (!$container->hasDefinition($entityNameMap[$classObject->getEntityName()])) {
                throw DependencyInjectionException::definitionNotFound($classObject->getEntityName());
            }

            $definition = $container->getDefinition($entityNameMap[$classObject->getEntityName()]);
            $definition->addMethodCall('addExtension', [new Reference($id)]);

            if (isset($channelNameMap[$classObject->getEntityName()])) {
                $definition = $container->getDefinition($channelNameMap[$classObject->getEntityName()]);
                $definition->addMethodCall('addExtension', [new Reference($id)]);
            }

            $extendedDefinition = self::PREFIX . $entityNameMap[$classObject->getEntityName()];

            if ($container->hasDefinition($extendedDefinition)) {
                $definition = $container->getDefinition($extendedDefinition);
                $definition->addMethodCall('addExtension', [new Reference($id)]);
            }
        }

        foreach ($container->findTaggedServiceIds('contena.bulk.entity.extension') as $id => $tags) {
            $definition = $container->getDefinition($id);

            /** @var class-string $className */
            $className = $definition->getClass() ?? $id;

            /** @var BulkEntityExtension $classObject */
            $classObject = new \ReflectionClass($className)->newInstanceWithoutConstructor();

            $entities = array_keys(iterator_to_array($classObject->collect()));

            foreach ($entities as $entity) {
                if (!\array_key_exists($entity, $entityNameMap)) {
                    throw DependencyInjectionException::definitionNotFound($entity);
                }

                if (!$container->hasDefinition($entityNameMap[$entity])) {
                    throw DependencyInjectionException::definitionNotFound($entity);
                }

                $filteredExtension = new Definition(FilteredBulkEntityExtension::class);
                $filteredExtension->addArgument($entity);
                $filteredExtension->addArgument(new Reference($id));

                $definition = $container->getDefinition($entityNameMap[$entity]);

                $definition->addMethodCall('addExtension', [$filteredExtension]);

                if (isset($channelNameMap[$entity])) {
                    $definition = $container->getDefinition($channelNameMap[$entity]);
                    $definition->addMethodCall('addExtension', [$filteredExtension]);
                }

                $extendedDefinition = self::PREFIX . $entityNameMap[$entity];

                if ($container->hasDefinition($extendedDefinition)) {
                    $definition = $container->getDefinition($extendedDefinition);
                    $definition->addMethodCall('addExtension', [$filteredExtension]);
                }
            }
        }
    }
}
