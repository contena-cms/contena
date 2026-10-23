<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Processing\Writer;

use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;

abstract class AbstractWriterFactory
{
    abstract public function create(ImportExportLogEntity $logEntity): AbstractWriter;

    abstract public function supports(ImportExportLogEntity $logEntity): bool;
}
