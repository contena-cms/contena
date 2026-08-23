<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule;

class RuleEvents
{
    final public const string RULE_WRITTEN_EVENT = 'rule.written';
    final public const string RULE_DELETED_EVENT = 'rule.deleted';
    final public const string RULE_LOADED_EVENT = 'rule.loaded';
    final public const string RULE_SEARCH_RESULT_LOADED_EVENT = 'rule.search.result.loaded';
    final public const string RULE_AGGREGATION_LOADED_EVENT = 'rule.aggregation.result.loaded';
    final public const string RULE_ID_SEARCH_RESULT_LOADED_EVENT = 'rule.id.search.result.loaded';
}
