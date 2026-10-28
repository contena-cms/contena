<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Processing\Writer;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Contena\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
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
