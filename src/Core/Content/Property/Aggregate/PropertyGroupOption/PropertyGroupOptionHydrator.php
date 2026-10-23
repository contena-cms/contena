<?php declare(strict_types=1);

namespace Contena\Core\Content\Property\Aggregate\PropertyGroupOption;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Uuid\Uuid;

class PropertyGroupOptionHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.groupId'])) {
            $entity->groupId = Uuid::fromBytesToHex($row[$root . '.groupId']);
        }
        if (isset($row[$root . '.colorHexCode'])) {
            $entity->colorHexCode = $row[$root . '.colorHexCode'];
        }
        if (isset($row[$root . '.mediaId'])) {
            $entity->mediaId = Uuid::fromBytesToHex($row[$root . '.mediaId']);
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }
        $entity->media = $this->manyToOne($row, $root, $definition->getField('media'), $context);
        $entity->group = $this->manyToOne($row, $root, $definition->getField('group'), $context);

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());
        $this->customFields($definition, $row, $root, $entity, $definition->getField('customFields'), $context);
        $this->manyToMany($row, $root, $entity, $definition->getField('blogProperties'));
        $this->manyToMany($row, $root, $entity, $definition->getField('blogOptions'));

        return $entity;
    }
}
