<?php declare(strict_types=1);

namespace Contena\Core\Framework\Log;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LogEntryEntity>
 */
class LogEntryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_log_entry_collection';
    }

    protected function getExpectedClass(): string
    {
        return LogEntryEntity::class;
    }
}
