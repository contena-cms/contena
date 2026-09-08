<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\ActionButtonTranslation;

use Contena\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Language\LanguageEntity;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class ActionButtonTranslationEntity extends Entity
{
    use EntityIdTrait;

    protected string $label;

    protected string $appActionButtonId;

    protected string $languageId;

    protected ?ActionButtonEntity $appActionButton = null;

    protected ?LanguageEntity $language = null;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getAppActionButtonId(): string
    {
        return $this->appActionButtonId;
    }

    public function setAppActionButtonId(string $appActionButtonId): void
    {
        $this->appActionButtonId = $appActionButtonId;
    }

    public function getAppActionButton(): ?ActionButtonEntity
    {
        return $this->appActionButton;
    }

    public function setAppActionButton(?ActionButtonEntity $appActionButton): void
    {
        $this->appActionButton = $appActionButton;
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
