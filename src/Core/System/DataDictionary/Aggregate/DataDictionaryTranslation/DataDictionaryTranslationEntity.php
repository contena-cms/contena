<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary\Aggregate\DataDictionaryTranslation;

use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\System\DataDictionary\DataDictionaryEntity;

class DataDictionaryTranslationEntity extends TranslationEntity
{
    protected string $dataDictionaryId;

    protected ?string $label = null;

    protected ?string $description = null;

    protected ?DataDictionaryEntity $dataDictionary = null;

    public function getDataDictionaryId(): string
    {
        return $this->dataDictionaryId;
    }

    public function setDataDictionaryId(string $dataDictionaryId): void
    {
        $this->dataDictionaryId = $dataDictionaryId;
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

    public function getDataDictionary(): ?DataDictionaryEntity
    {
        return $this->dataDictionary;
    }

    public function setDataDictionary(DataDictionaryEntity $dataDictionary): void
    {
        $this->dataDictionary = $dataDictionary;
    }
}
