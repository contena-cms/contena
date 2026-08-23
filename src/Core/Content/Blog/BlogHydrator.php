<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Uuid\Uuid;

class BlogHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.versionId'])) {
            $entity->versionId = Uuid::fromBytesToHex($row[$root . '.versionId']);
        }
        if (isset($row[$root . '.coverId'])) {
            $entity->coverId = Uuid::fromBytesToHex($row[$root . '.coverId']);
        }
        if (isset($row[$root . '.openGraphMediaId'])) {
            $entity->openGraphMediaId = Uuid::fromBytesToHex($row[$root . '.openGraphMediaId']);
        }
        if (isset($row[$root . '.autoIncrement'])) {
            $entity->autoIncrement = (int) $row[$root . '.autoIncrement'];
        }
        if (isset($row[$root . '.active'])) {
            $entity->active = (bool) $row[$root . '.active'];
        }
        if (isset($row[$root . '.type'])) {
            $entity->type = $row[$root . '.type'];
        }
        if (isset($row[$root . '.releaseDate'])) {
            $entity->releaseDate = new \DateTimeImmutable($row[$root . '.releaseDate']);
        }
        if (\array_key_exists($root . '.categoryTree', $row)) {
            $entity->categoryTree = $definition->decode('categoryTree', self::value($row, $root, 'categoryTree'));
        }
        if (\array_key_exists($root . '.tagIds', $row)) {
            $entity->tagIds = $definition->decode('tagIds', self::value($row, $root, 'tagIds'));
        }
        if (\array_key_exists($root . '.categoryIds', $row)) {
            $entity->categoryIds = $definition->decode('categoryIds', self::value($row, $root, 'categoryIds'));
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }

        $entity->cover = $this->manyToOne($row, $root, $definition->getField('cover'), $context);
        $entity->openGraphMedia = $this->manyToOne($row, $root, $definition->getField('openGraphMedia'), $context);
        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());
        $this->customFields($definition, $row, $root, $entity, $definition->getField('customFields'), $context);
        $this->manyToMany($row, $root, $entity, $definition->getField('categories'));
        $this->manyToMany($row, $root, $entity, $definition->getField('categoriesRo'));
        $this->manyToMany($row, $root, $entity, $definition->getField('tags'));

        return $entity;
    }
}
