<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<DataDictionaryItemEntity>
 */
class DataDictionaryItemCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'data_dictionary_item_collection';
    }

    public function filterByCode(string $code): self
    {
        return $this->filter(static fn (DataDictionaryItemEntity $item) => $item->getCode() === $code);
    }

    protected function getExpectedClass(): string
    {
        return DataDictionaryItemEntity::class;
    }
}
