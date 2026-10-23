<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Aggregate\ImportExportLog;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ImportExportLogEntity>
 *
 * @codeCoverageIgnore
 */
class ImportExportLogCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'import_export_profile_log_collection';
    }

    protected function getExpectedClass(): string
    {
        return ImportExportLogEntity::class;
    }
}
