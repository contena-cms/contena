<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogCrossSelling;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

class BlogCrossSellingHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.position'])) {
            $entity->position = (int) $row[$root . '.position'];
        }
        if (isset($row[$root . '.sortBy'])) {
            $entity->sortBy = $row[$root . '.sortBy'];
        }
        if (isset($row[$root . '.sortDirection'])) {
            $entity->sortDirection = $row[$root . '.sortDirection'];
        }
        if (isset($row[$root . '.type'])) {
            $entity->type = $row[$root . '.type'];
        }
        if (isset($row[$root . '.active'])) {
            $entity->active = (bool) $row[$root . '.active'];
        }
        if (isset($row[$root . '.limit'])) {
            $entity->limit = (int) $row[$root . '.limit'];
        }
        if (isset($row[$root . '.blogId'])) {
            $entity->blogId = Uuid::fromBytesToHex($row[$root . '.blogId']);
        }
        if (isset($row[$root . '.contentStreamId'])) {
            $entity->contentStreamId = Uuid::fromBytesToHex($row[$root . '.contentStreamId']);
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }
        $entity->blog = $this->manyToOne($row, $root, $definition->getField('blog'), $context);
        $entity->contentStream = $this->manyToOne($row, $root, $definition->getField('contentStream'), $context);

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());

        return $entity;
    }
}
