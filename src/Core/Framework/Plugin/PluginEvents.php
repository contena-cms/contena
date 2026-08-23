<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin;

class PluginEvents
{
    final public const string PLUGIN_WRITTEN_EVENT = 'plugin.written';

    final public const string PLUGIN_DELETED_EVENT = 'plugin.deleted';

    final public const string PLUGIN_LOADED_EVENT = 'plugin.loaded';

    final public const string PLUGIN_SEARCH_RESULT_LOADED_EVENT = 'plugin.search.result.loaded';

    final public const string PLUGIN_AGGREGATION_LOADED_EVENT = 'plugin.aggregation.result.loaded';

    final public const string PLUGIN_ID_SEARCH_RESULT_LOADED_EVENT = 'plugin.id.search.result.loaded';
}
