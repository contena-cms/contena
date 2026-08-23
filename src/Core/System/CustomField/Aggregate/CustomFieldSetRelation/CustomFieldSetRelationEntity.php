<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField\Aggregate\CustomFieldSetRelation;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;

class CustomFieldSetRelationEntity extends Entity
{
    use EntityIdTrait;

    protected string $entityName;

    protected string $customFieldSetId;

    protected ?CustomFieldSetEntity $customFieldSet = null;

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setEntityName(string $entityName): void
    {
        $this->entityName = $entityName;
    }

    public function getCustomFieldSetId(): string
    {
        return $this->customFieldSetId;
    }

    public function setCustomFieldSetId(string $customFieldSetId): void
    {
        $this->customFieldSetId = $customFieldSetId;
    }

    public function getCustomFieldSet(): ?CustomFieldSetEntity
    {
        return $this->customFieldSet;
    }

    public function setCustomFieldSet(CustomFieldSetEntity $customFieldSet): void
    {
        $this->customFieldSet = $customFieldSet;
    }
}
