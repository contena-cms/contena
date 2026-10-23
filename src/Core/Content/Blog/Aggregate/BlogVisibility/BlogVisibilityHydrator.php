<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogVisibility;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Uuid\Uuid;

class BlogVisibilityHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.blogId'])) {
            $entity->blogId = Uuid::fromBytesToHex($row[$root . '.blogId']);
        }
        if (isset($row[$root . '.channelId'])) {
            $entity->channelId = Uuid::fromBytesToHex($row[$root . '.channelId']);
        }
        if (isset($row[$root . '.visibility'])) {
            $entity->visibility = (int) $row[$root . '.visibility'];
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }
        $entity->channel = $this->manyToOne($row, $root, $definition->getField('channel'), $context);
        $entity->blog = $this->manyToOne($row, $root, $definition->getField('blog'), $context);

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());

        return $entity;
    }
}
