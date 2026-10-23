<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Processing\Writer;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;

class CsvFileWriterFactory extends AbstractWriterFactory
{
    /**
     * @internal
     */
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    public function create(ImportExportLogEntity $logEntity): AbstractWriter
    {
        return new CsvFileWriter($this->filesystem);
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return $logEntity->getProfile()?->getFileType() === 'text/csv';
    }
}
