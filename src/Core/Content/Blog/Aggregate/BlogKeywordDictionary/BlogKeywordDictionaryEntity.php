<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogKeywordDictionary;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Language\LanguageEntity;

class BlogKeywordDictionaryEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $languageId;

    protected string $keyword;

    protected string $reversed;

    protected ?LanguageEntity $language = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function setKeyword(string $keyword): void
    {
        $this->keyword = $keyword;
    }

    public function getReversed(): string
    {
        return $this->reversed;
    }

    public function setReversed(string $reversed): void
    {
        $this->reversed = $reversed;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(?LanguageEntity $language): void
    {
        $this->language = $language;
    }
}
