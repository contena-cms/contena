<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem\DataDictionaryItemCollection;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryTranslation\DataDictionaryTranslationCollection;

class DataDictionaryEntity extends Entity
{
    use EntityIdTrait;

    protected string $technicalName;

    protected bool $active;

    protected bool $systemLocked;

    protected ?string $label = null;

    protected ?string $description = null;

    protected ?DataDictionaryItemCollection $items = null;

    protected ?DataDictionaryTranslationCollection $translations = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isSystemLocked(): bool
    {
        return $this->systemLocked;
    }

    public function setSystemLocked(bool $systemLocked): void
    {
        $this->systemLocked = $systemLocked;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getItems(): ?DataDictionaryItemCollection
    {
        return $this->items;
    }

    public function setItems(DataDictionaryItemCollection $items): void
    {
        $this->items = $items;
    }

    public function getTranslations(): ?DataDictionaryTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(DataDictionaryTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
