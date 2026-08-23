<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItemTranslation;

use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem\DataDictionaryItemEntity;

class DataDictionaryItemTranslationEntity extends TranslationEntity
{
    protected string $dataDictionaryItemId;

    protected ?string $label = null;

    protected ?string $description = null;

    protected ?DataDictionaryItemEntity $dataDictionaryItem = null;

    public function getDataDictionaryItemId(): string
    {
        return $this->dataDictionaryItemId;
    }

    public function setDataDictionaryItemId(string $dataDictionaryItemId): void
    {
        $this->dataDictionaryItemId = $dataDictionaryItemId;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
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

    public function getDataDictionaryItem(): ?DataDictionaryItemEntity
    {
        return $this->dataDictionaryItem;
    }

    public function setDataDictionaryItem(DataDictionaryItemEntity $dataDictionaryItem): void
    {
        $this->dataDictionaryItem = $dataDictionaryItem;
    }
}
