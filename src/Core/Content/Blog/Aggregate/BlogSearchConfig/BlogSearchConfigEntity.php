<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogSearchConfig;

use Contena\Core\Content\Blog\Aggregate\BlogSearchConfigField\BlogSearchConfigFieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Language\LanguageEntity;

class BlogSearchConfigEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $languageId;

    protected bool $andLogic;

    protected int $minSearchLength;

    /**
     * @var array<string>|null
     */
    protected ?array $excludedTerms = null;

    protected ?LanguageEntity $language = null;

    protected ?BlogSearchConfigFieldCollection $configFields = null;

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

    public function getAndLogic(): bool
    {
        return $this->andLogic;
    }

    public function setAndLogic(bool $andLogic): void
    {
        $this->andLogic = $andLogic;
    }

    public function getMinSearchLength(): int
    {
        return $this->minSearchLength;
    }

    public function setMinSearchLength(int $minSearchLength): void
    {
        $this->minSearchLength = $minSearchLength;
    }

    /**
     * @return array<string>|null
     */
    public function getExcludedTerms(): ?array
    {
        return $this->excludedTerms;
    }

    /**
     * @param array<string>|null $excludedTerms
     */
    public function setExcludedTerms(?array $excludedTerms): void
    {
        $this->excludedTerms = $excludedTerms;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getConfigFields(): ?BlogSearchConfigFieldCollection
    {
        return $this->configFields;
    }

    public function setConfigFields(BlogSearchConfigFieldCollection $configFields): void
    {
        $this->configFields = $configFields;
    }

    public function getApiAlias(): string
    {
        return 'blog_search_config';
    }
}
