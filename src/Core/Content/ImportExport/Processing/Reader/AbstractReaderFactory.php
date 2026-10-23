<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Processing\Reader;

use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;

abstract class AbstractReaderFactory
{
    abstract public function create(ImportExportLogEntity $logEntity): AbstractReader;

    abstract public function supports(ImportExportLogEntity $logEntity): bool;
}
