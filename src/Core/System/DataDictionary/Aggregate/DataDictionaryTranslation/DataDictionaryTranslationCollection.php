<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary\Aggregate\DataDictionaryTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<DataDictionaryTranslationEntity>
 */
class DataDictionaryTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'data_dictionary_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return DataDictionaryTranslationEntity::class;
    }
}
