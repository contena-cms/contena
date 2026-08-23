<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItemTranslation\DataDictionaryItemTranslationCollection;
use Contena\Core\System\DataDictionary\DataDictionaryEntity;

class DataDictionaryItemEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $dictionaryId;

    protected ?string $parentId = null;

    protected int $level;

    protected ?string $path = null;

    protected int $childCount;

    protected string $code;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $value = null;

    protected int $position;

    protected bool $active;

    protected bool $systemLocked;

    protected ?string $label = null;

    protected ?string $description = null;

    protected ?DataDictionaryEntity $dictionary = null;

    protected ?DataDictionaryItemEntity $parent = null;

    protected ?DataDictionaryItemCollection $children = null;

    protected ?DataDictionaryItemTranslationCollection $translations = null;

    public function getDictionaryId(): string
    {
        return $this->dictionaryId;
    }

    public function setDictionaryId(string $dictionaryId): void
    {
        $this->dictionaryId = $dictionaryId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    public function getChildCount(): int
    {
        return $this->childCount;
    }

    public function setChildCount(int $childCount): void
    {
        $this->childCount = $childCount;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getValue(): ?array
    {
        return $this->value;
    }

    /**
     * @param array<string, mixed>|null $value
     */
    public function setValue(?array $value): void
    {
        $this->value = $value;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
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

    public function getDictionary(): ?DataDictionaryEntity
    {
        return $this->dictionary;
    }

    public function setDictionary(DataDictionaryEntity $dictionary): void
    {
        $this->dictionary = $dictionary;
    }

    public function getParent(): ?DataDictionaryItemEntity
    {
        return $this->parent;
    }

    public function setParent(?DataDictionaryItemEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?DataDictionaryItemCollection
    {
        return $this->children;
    }

    public function setChildren(DataDictionaryItemCollection $children): void
    {
        $this->children = $children;
    }

    public function getTranslations(): ?DataDictionaryItemTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(DataDictionaryItemTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
