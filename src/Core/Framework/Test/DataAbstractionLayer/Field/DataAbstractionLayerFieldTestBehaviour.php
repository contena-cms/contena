<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Field;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\Exception\MappingEntityClassesException;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\VersionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait DataAbstractionLayerFieldTestBehaviour
{
    /**
     * @var list<class-string<EntityDefinition>>
     */
    private array $addedDefinitions = [];

    /**
     * @var list<class-string<EntityExtension>>
     */
    private array $addedExtensions = [];

    /**
     * @var array<class-string<EntityExtension>, class-string<EntityDefinition>>
     */
    private array $extensionDefinitionMap = [];

    protected function tearDown(): void
    {
        $this->removeExtension(...$this->addedExtensions);
        $this->removeDefinitions(...$this->addedDefinitions);
        $this->addedDefinitions = [];
        $this->addedExtensions = [];
        $this->extensionDefinitionMap = [];
    }

    abstract protected static function getContainer(): ContainerInterface;

    /**
     * @param class-string<EntityDefinition> ...$definitionClasses
     */
    protected function registerDefinition(string ...$definitionClasses): EntityDefinition
    {
        $ret = null;
        $container = static::getContainer();

        foreach ($definitionClasses as $definitionClass) {
            if ($container->has($definitionClass)) {
                /** @var EntityDefinition $definition */
                $definition = $container->get($definitionClass);
            } else {
                $this->addedDefinitions[] = $definitionClass;
                $definition = new $definitionClass();

                $repoId = $definition->getEntityName() . '.repository';
                if (!$container->has($repoId)) {
                    $repository = new EntityRepository(
                        $definition,
                        $container->get(EntityReaderInterface::class),
                        $container->get(VersionManager::class),
                        $container->get(EntitySearcherInterface::class),
                        $container->get(EntityAggregatorInterface::class),
                        $container->get('event_dispatcher'),
                        $container->get(EntityLoadedEventFactory::class)
                    );

                    $container->set($repoId, $repository);
                }
            }

            $container->get(DefinitionInstanceRegistry::class)->register($definition);

            if ($ret === null) {
                $ret = $definition;
            }
        }

        if (!$ret) {
            throw new \InvalidArgumentException('Need at least one definition class to register.');
        }

        return $ret;
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     * @param class-string<EntityExtension> ...$extensionsClasses
     */
    protected function registerDefinitionWithExtensions(string $definitionClass, string ...$extensionsClasses): EntityDefinition
    {
        $definition = $this->registerDefinition($definitionClass);
        $this->registerDefinitionExtensions($extensionsClasses, $definitionClass, $definition);

        return $definition;
    }

    /**
     * @param class-string<EntityExtension> ...$extensionsClasses
     */
    private function removeExtension(string ...$extensionsClasses): void
    {
        $container = static::getContainer();

        foreach ($extensionsClasses as $extensionsClass) {
            $extension = new $extensionsClass();
            TestCase::assertArrayHasKey($extensionsClass, $this->extensionDefinitionMap, \sprintf('Trying to remove not registered extension "%s".', $extensionsClass));

            $definitionClass = $this->extensionDefinitionMap[$extensionsClass];
            if ($container->has($definitionClass)) {
                /** @var EntityDefinition $definition */
                $definition = $container->get($definitionClass);

                $definition->removeExtension($extension);
            }
        }
    }

    /**
     * @param class-string<EntityDefinition> ...$definitionClasses
     */
    private function removeDefinitions(string ...$definitionClasses): void
    {
        $registry = static::getContainer()->get(DefinitionInstanceRegistry::class);

        foreach ($definitionClasses as $definitionClass) {
            $definition = new $definitionClass();

            \Closure::bind(function () use ($definition): void {
                unset(
                    $this->definitions[$definition->getEntityName()],
                    $this->repositoryMap[$definition->getEntityName()],
                );

                try {
                    unset($this->entityClassMapping[$definition->getEntityClass()]);
                } catch (MappingEntityClassesException) {
                }
            }, $registry, $registry)();
        }
    }

    /**
     * @internal
     *
     * @param array<class-string<EntityExtension>> $extensionsClasses
     * @param class-string<EntityDefinition> $definitionClass
     */
    private function registerDefinitionExtensions(array $extensionsClasses, string $definitionClass, EntityDefinition $definition): void
    {
        $container = static::getContainer();

        foreach ($extensionsClasses as $extensionsClass) {
            $this->addedExtensions[] = $extensionsClass;
            $this->extensionDefinitionMap[$extensionsClass] = $definitionClass;

            if ($container->has($extensionsClass)) {
                /** @var EntityExtension $extension */
                $extension = $container->get($extensionsClass);
            } else {
                $extension = new $extensionsClass();
                $container->set($extensionsClass, $extension);
            }

            $definition->addExtension($extension);
        }
    }
}
