<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Uuid\Uuid;

class BlogSearchKeywordHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.tenantId'])) {
            $entity->tenantId = Uuid::fromBytesToHex($row[$root . '.tenantId']);
        }
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.versionId'])) {
            $entity->versionId = Uuid::fromBytesToHex($row[$root . '.versionId']);
        }
        if (isset($row[$root . '.languageId'])) {
            $entity->languageId = Uuid::fromBytesToHex($row[$root . '.languageId']);
        }
        if (isset($row[$root . '.blogId'])) {
            $entity->blogId = Uuid::fromBytesToHex($row[$root . '.blogId']);
        }
        if (isset($row[$root . '.keyword'])) {
            $entity->keyword = $row[$root . '.keyword'];
        }
        if (isset($row[$root . '.ranking'])) {
            $entity->ranking = (float) $row[$root . '.ranking'];
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }
        $entity->blog = $this->manyToOne($row, $root, $definition->getField('blog'), $context);
        $entity->language = $this->manyToOne($row, $root, $definition->getField('language'), $context);

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());

        return $entity;
    }
}
