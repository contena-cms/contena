<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event\EventData;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;

class EntityCollectionType implements EventDataType
{
    final public const string TYPE = 'collection';

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    public function __construct(private readonly string $definitionClass)
    {
    }

    /**
     * @return array{type: string, entityClass: class-string<EntityDefinition>}
     */
    public function toArray(): array
    {
        return [
            'type' => self::TYPE,
            'entityClass' => $this->definitionClass,
        ];
    }
}
