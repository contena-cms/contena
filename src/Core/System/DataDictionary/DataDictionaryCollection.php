<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<DataDictionaryEntity>
 */
class DataDictionaryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'data_dictionary_collection';
    }

    public function filterByTechnicalName(string $technicalName): self
    {
        return $this->filter(static fn (DataDictionaryEntity $dictionary) => $dictionary->getTechnicalName() === $technicalName);
    }

    protected function getExpectedClass(): string
    {
        return DataDictionaryEntity::class;
    }
}
