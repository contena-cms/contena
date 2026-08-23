<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event\EventData;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\FrameworkException;

class EntityType implements EventDataType
{
    final public const string TYPE = 'entity';

    /**
     * @var class-string<EntityDefinition>
     */
    private readonly string $definitionClass;

    private readonly string $entityName;

    /**
     * @param class-string<EntityDefinition>|EntityDefinition $definitionClass
     */
    public function __construct(string|EntityDefinition $definitionClass)
    {
        if (\is_string($definitionClass) && !\is_a($definitionClass, EntityDefinition::class, true)) {
            throw FrameworkException::invalidEventData(
                'Expected an instance of ' . EntityDefinition::class . ' or a class name that extends ' . EntityDefinition::class
            );
        }

        $entityDefinition = $definitionClass instanceof EntityDefinition ? $definitionClass : new $definitionClass();

        $this->definitionClass = $entityDefinition::class;
        $this->entityName = $entityDefinition->getEntityName();
    }

    /**
     * @return array{type: string, entityClass: class-string<EntityDefinition>, entityName: string}
     */
    public function toArray(): array
    {
        return [
            'type' => self::TYPE,
            'entityClass' => $this->definitionClass,
            'entityName' => $this->entityName,
        ];
    }
}
