<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Version;

class VersionEvents
{
    final public const string VERSION_WRITTEN_EVENT = 'version.written';

    final public const string VERSION_DELETED_EVENT = 'version.deleted';

    final public const string VERSION_LOADED_EVENT = 'version.loaded';

    final public const string VERSION_SEARCH_RESULT_LOADED_EVENT = 'version.search.result.loaded';

    final public const string VERSION_AGGREGATION_LOADED_EVENT = 'version.aggregation.result.loaded';

    final public const string VERSION_ID_SEARCH_RESULT_LOADED_EVENT = 'version.id.search.result.loaded';

    final public const string VERSION_COMMIT_WRITTEN_EVENT = 'version_commit.written';

    final public const string VERSION_COMMIT_DELETED_EVENT = 'version_commit.deleted';

    final public const string VERSION_COMMIT_LOADED_EVENT = 'version_commit.loaded';

    final public const string VERSION_COMMIT_SEARCH_RESULT_LOADED_EVENT = 'version_commit.search.result.loaded';

    final public const string VERSION_COMMIT_AGGREGATION_LOADED_EVENT = 'version_commit.aggregation.result.loaded';

    final public const string VERSION_COMMIT_ID_SEARCH_RESULT_LOADED_EVENT = 'version_commit.id.search.result.loaded';

    final public const string VERSION_COMMIT_DATA_WRITTEN_EVENT = 'version_commit_data.written';

    final public const string VERSION_COMMIT_DATA_DELETED_EVENT = 'version_commit_data.deleted';

    final public const string VERSION_COMMIT_DATA_LOADED_EVENT = 'version_commit_data.loaded';

    final public const string VERSION_COMMIT_DATA_SEARCH_RESULT_LOADED_EVENT = 'version_commit_data.search.result.loaded';

    final public const string VERSION_COMMIT_DATA_AGGREGATION_LOADED_EVENT = 'version_commit_data.aggregation.result.loaded';

    final public const string VERSION_COMMIT_DATA_ID_SEARCH_RESULT_LOADED_EVENT = 'version_commit_data.id.search.result.loaded';
}
