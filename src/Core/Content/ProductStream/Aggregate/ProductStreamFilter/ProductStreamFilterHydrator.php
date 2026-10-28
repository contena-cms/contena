<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductStream\Aggregate\ProductStreamFilter;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

#[Package('inventory')]
class ProductStreamFilterHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.productStreamId'])) {
            $entity->productStreamId = Uuid::fromBytesToHex($row[$root . '.productStreamId']);
        }
        if (isset($row[$root . '.parentId'])) {
            $entity->parentId = Uuid::fromBytesToHex($row[$root . '.parentId']);
        }
        if (isset($row[$root . '.type'])) {
            $entity->type = $row[$root . '.type'];
        }
        if (isset($row[$root . '.field'])) {
            $entity->field = $row[$root . '.field'];
        }
        if (isset($row[$root . '.operator'])) {
            $entity->operator = $row[$root . '.operator'];
        }
        if (\array_key_exists($root . '.value', $row)) {
            $entity->value = $definition->decode('value', self::value($row, $root, 'value'));
        }
        if (\array_key_exists($root . '.parameters', $row)) {
            $entity->parameters = $definition->decode('parameters', self::value($row, $root, 'parameters'));
        }
        if (isset($row[$root . '.position'])) {
            $entity->position = (int) $row[$root . '.position'];
        }
        if (\array_key_exists($root . '.customFields', $row)) {
            $entity->customFields = $definition->decode('customFields', self::value($row, $root, 'customFields'));
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }
        $entity->productStream = $this->manyToOne($row, $root, $definition->getField('productStream'), $context);

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());
        $this->customFields($definition, $row, $root, $entity, $definition->getField('customFields'), $context);

        return $entity;
    }
}
