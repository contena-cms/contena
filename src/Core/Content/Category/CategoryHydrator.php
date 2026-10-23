<?php declare(strict_types=1);

namespace Contena\Core\Content\Category;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Uuid\Uuid;

class CategoryHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.versionId'])) {
            $entity->versionId = Uuid::fromBytesToHex($row[$root . '.versionId']);
        }
        if (isset($row[$root . '.parentId'])) {
            $entity->parentId = Uuid::fromBytesToHex($row[$root . '.parentId']);
        }
        if (isset($row[$root . '.afterCategoryId'])) {
            $entity->afterCategoryId = Uuid::fromBytesToHex($row[$root . '.afterCategoryId']);
        }
        if (isset($row[$root . '.mediaId'])) {
            $entity->mediaId = Uuid::fromBytesToHex($row[$root . '.mediaId']);
        }
        if (isset($row[$root . '.displayNestedBlogs'])) {
            $entity->displayNestedBlogs = (bool) $row[$root . '.displayNestedBlogs'];
        }
        if (isset($row[$root . '.autoIncrement'])) {
            $entity->autoIncrement = (int) $row[$root . '.autoIncrement'];
        }
        if (isset($row[$root . '.level'])) {
            $entity->level = (int) $row[$root . '.level'];
        }
        if (\array_key_exists($root . '.path', $row)) {
            $entity->path = $definition->decode('path', self::value($row, $root, 'path'));
        }
        if (isset($row[$root . '.childCount'])) {
            $entity->childCount = (int) $row[$root . '.childCount'];
        }
        if (isset($row[$root . '.type'])) {
            $entity->type = $row[$root . '.type'];
        }
        if (isset($row[$root . '.blogAssignmentType'])) {
            $entity->blogAssignmentType = $row[$root . '.blogAssignmentType'];
        }
        if (isset($row[$root . '.visible'])) {
            $entity->visible = (bool) $row[$root . '.visible'];
        }
        if (isset($row[$root . '.active'])) {
            $entity->active = (bool) $row[$root . '.active'];
        }
        if (isset($row[$root . '.cmsPageId'])) {
            $entity->cmsPageId = Uuid::fromBytesToHex($row[$root . '.cmsPageId']);
        }
        if (isset($row[$root . '.blogStreamId'])) {
            $entity->blogStreamId = Uuid::fromBytesToHex($row[$root . '.blogStreamId']);
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }

        if (isset($row[$root . '.customEntityTypeId'])) {
            $entity->customEntityTypeId = Uuid::fromBytesToHex($row[$root . '.customEntityTypeId']);
        }

        $entity->media = $this->manyToOne($row, $root, $definition->getField('media'), $context);
        $entity->cmsPage = $this->manyToOne($row, $root, $definition->getField('cmsPage'), $context);
        $entity->blogStream = $this->manyToOne($row, $root, $definition->getField('blogStream'), $context);

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());
        $this->customFields($definition, $row, $root, $entity, $definition->getField('customFields'), $context);
        $this->manyToMany($row, $root, $entity, $definition->getField('blogs'));
        $this->manyToMany($row, $root, $entity, $definition->getField('nestedBlogs'));
        $this->manyToMany($row, $root, $entity, $definition->getField('tags'));

        return $entity;
    }
}
