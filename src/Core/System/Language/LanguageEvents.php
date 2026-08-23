<?php declare(strict_types=1);

namespace Contena\Core\System\Language;

class LanguageEvents
{
    final public const string LANGUAGE_WRITTEN_EVENT = 'language.written';

    final public const string LANGUAGE_DELETED_EVENT = 'language.deleted';

    final public const string LANGUAGE_LOADED_EVENT = 'language.loaded';

    final public const string LANGUAGE_SEARCH_RESULT_LOADED_EVENT = 'language.search.result.loaded';

    final public const string LANGUAGE_AGGREGATION_LOADED_EVENT = 'language.aggregation.result.loaded';

    final public const string LANGUAGE_ID_SEARCH_RESULT_LOADED_EVENT = 'language.id.search.result.loaded';
}
