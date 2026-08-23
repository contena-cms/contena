<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow;

class FlowEvents
{
    final public const string FLOW_WRITTEN_EVENT = 'flow.written';
    final public const string FLOW_DELETED_EVENT = 'flow.deleted';
    final public const string FLOW_LOADED_EVENT = 'flow.loaded';
    final public const string FLOW_SEARCH_RESULT_LOADED_EVENT = 'flow.search.result.loaded';
}
