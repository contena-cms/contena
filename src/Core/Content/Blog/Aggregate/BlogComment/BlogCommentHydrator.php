<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogComment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Uuid\Uuid;

class BlogCommentHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }
        if (isset($row[$root . '.dataScopeId'])) {
            $entity->dataScopeId = Uuid::fromBytesToHex($row[$root . '.dataScopeId']);
        }
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.blogId'])) {
            $entity->blogId = Uuid::fromBytesToHex($row[$root . '.blogId']);
        }
        if (isset($row[$root . '.memberId'])) {
            $entity->memberId = Uuid::fromBytesToHex($row[$root . '.memberId']);
        }
        if (isset($row[$root . '.channelId'])) {
            $entity->channelId = Uuid::fromBytesToHex($row[$root . '.channelId']);
        }
        if (isset($row[$root . '.languageId'])) {
            $entity->languageId = Uuid::fromBytesToHex($row[$root . '.languageId']);
        }
        if (isset($row[$root . '.parentId'])) {
            $entity->parentId = Uuid::fromBytesToHex($row[$root . '.parentId']);
        }
        if (isset($row[$root . '.externalUser'])) {
            $entity->externalUser = $row[$root . '.externalUser'];
        }
        if (isset($row[$root . '.externalEmail'])) {
            $entity->externalEmail = $row[$root . '.externalEmail'];
        }
        if (\array_key_exists($root . '.content', $row)) {
            $entity->content = $definition->decode('content', self::value($row, $root, 'content'));
        }
        if (isset($row[$root . '.status'])) {
            $entity->status = (bool) $row[$root . '.status'];
        }
        if (\array_key_exists($root . '.customFields', $row)) {
            $entity->customFields = $definition->decode('customFields', self::value($row, $root, 'customFields'));
        }
        $entity->blog = $this->manyToOne($row, $root, $definition->getField('blog'), $context);
        $entity->member = $this->manyToOne($row, $root, $definition->getField('member'), $context);
        $entity->channel = $this->manyToOne($row, $root, $definition->getField('channel'), $context);
        $entity->language = $this->manyToOne($row, $root, $definition->getField('language'), $context);

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());
        $this->customFields($definition, $row, $root, $entity, $definition->getField('customFields'), $context);

        return $entity;
    }
}
