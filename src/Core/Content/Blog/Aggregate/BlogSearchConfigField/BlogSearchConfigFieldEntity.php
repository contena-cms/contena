<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogSearchConfigField;

use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\CustomField\CustomFieldEntity;

class BlogSearchConfigFieldEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $searchConfigId;

    protected ?string $customFieldId = null;

    protected string $field;

    protected bool $tokenize;

    protected bool $searchable;

    protected bool $useExactSubfield;

    protected int $ranking;

    protected ?BlogSearchConfigEntity $searchConfig = null;

    protected ?CustomFieldEntity $customField = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getSearchConfigId(): string
    {
        return $this->searchConfigId;
    }

    public function setSearchConfigId(string $searchConfigId): void
    {
        $this->searchConfigId = $searchConfigId;
    }

    public function getCustomFieldId(): ?string
    {
        return $this->customFieldId;
    }

    public function setCustomFieldId(?string $customFieldId): void
    {
        $this->customFieldId = $customFieldId;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function getTokenize(): bool
    {
        return $this->tokenize;
    }

    public function setTokenize(bool $tokenize): void
    {
        $this->tokenize = $tokenize;
    }

    public function getSearchable(): bool
    {
        return $this->searchable;
    }

    public function setSearchable(bool $searchable): void
    {
        $this->searchable = $searchable;
    }

    public function getUseExactSubfield(): bool
    {
        return $this->useExactSubfield;
    }

    public function setUseExactSubfield(bool $useExactSubfield): void
    {
        $this->useExactSubfield = $useExactSubfield;
    }

    public function getRanking(): int
    {
        return $this->ranking;
    }

    public function setRanking(int $ranking): void
    {
        $this->ranking = $ranking;
    }

    public function getSearchConfig(): ?BlogSearchConfigEntity
    {
        return $this->searchConfig;
    }

    public function setSearchConfig(BlogSearchConfigEntity $searchConfig): void
    {
        $this->searchConfig = $searchConfig;
    }

    public function getCustomField(): ?CustomFieldEntity
    {
        return $this->customField;
    }

    public function setCustomField(?CustomFieldEntity $customField): void
    {
        $this->customField = $customField;
    }

    public function getApiAlias(): string
    {
        return 'blog_search_config_field';
    }
}
