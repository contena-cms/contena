<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Processing\Pipe;

use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
abstract class AbstractPipeFactory
{
    abstract public function create(ImportExportLogEntity $logEntity): AbstractPipe;

    abstract public function supports(ImportExportLogEntity $logEntity): bool;
}
