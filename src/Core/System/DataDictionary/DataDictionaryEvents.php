<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary;

use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem\DataDictionaryItemDefinition;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItemTranslation\DataDictionaryItemTranslationDefinition;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryTranslation\DataDictionaryTranslationDefinition;

class DataDictionaryEvents
{
    final public const string DATA_DICTIONARY_WRITTEN_EVENT = DataDictionaryDefinition::ENTITY_NAME . '.written';

    final public const string DATA_DICTIONARY_DELETED_EVENT = DataDictionaryDefinition::ENTITY_NAME . '.deleted';

    final public const string DATA_DICTIONARY_TRANSLATION_WRITTEN_EVENT = DataDictionaryTranslationDefinition::ENTITY_NAME . '.written';

    final public const string DATA_DICTIONARY_TRANSLATION_DELETED_EVENT = DataDictionaryTranslationDefinition::ENTITY_NAME . '.deleted';

    final public const string DATA_DICTIONARY_ITEM_WRITTEN_EVENT = DataDictionaryItemDefinition::ENTITY_NAME . '.written';

    final public const string DATA_DICTIONARY_ITEM_DELETED_EVENT = DataDictionaryItemDefinition::ENTITY_NAME . '.deleted';

    final public const string DATA_DICTIONARY_ITEM_TRANSLATION_WRITTEN_EVENT = DataDictionaryItemTranslationDefinition::ENTITY_NAME . '.written';

    final public const string DATA_DICTIONARY_ITEM_TRANSLATION_DELETED_EVENT = DataDictionaryItemTranslationDefinition::ENTITY_NAME . '.deleted';
}
