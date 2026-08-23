<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItemTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<DataDictionaryItemTranslationEntity>
 */
class DataDictionaryItemTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'data_dictionary_item_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return DataDictionaryItemTranslationEntity::class;
    }
}
