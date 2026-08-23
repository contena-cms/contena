<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer;

use Contena\Core\System\Language\LanguageEntity;

class TranslationEntity extends Entity
{
    protected string $languageId;

    protected ?LanguageEntity $language = null;

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }
}
