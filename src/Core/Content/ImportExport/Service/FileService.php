<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Service;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Contena\Core\Content\ImportExport\ImportExportException;
use Contena\Core\Content\ImportExport\ImportExportProfileEntity;
use Contena\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Contena\Core\Content\ImportExport\Processing\Writer\CsvFileWriter;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileService extends AbstractFileService
{
    private readonly CsvFileWriter $writer;

    /**
     * @internal
     *
     * @param EntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly EntityRepository $fileRepository
    ) {
        $this->writer = new CsvFileWriter($filesystem);
    }

    public function getDecorated(): AbstractFileService
    {
        throw new DecorationPatternException(self::class);
    }

    public function storeFile(Context $context, \DateTimeInterface $expireDate, ?string $sourcePath, ?string $originalFileName, string $activity, ?string $path = null): ImportExportFileEntity
    {
        $id = Uuid::randomHex();
        $path ??= $activity . '/' . ImportExportFileEntity::buildPath($id);
        if ($sourcePath !== null && $sourcePath !== '') {
            if (!is_readable($sourcePath)) {
                throw ImportExportException::fileNotReadable($sourcePath);
            }
            $sourceStream = fopen($sourcePath, 'r');
            if (!\is_resource($sourceStream)) {
                throw ImportExportException::fileNotReadable($sourcePath);
            }
            $this->filesystem->writeStream($path, $sourceStream);

            if (\is_resource($sourceStream)) {
                fclose($sourceStream);
            }
        } else {
            $this->filesystem->write($path, '');
        }

        $fileData = [
            'id' => $id,
            'originalName' => $originalFileName,
            'path' => $path,
            'size' => $this->filesystem->fileSize($path),
            'expireDate' => $expireDate,
            'accessToken' => null,
        ];

        $this->fileRepository->create([$fileData], $context);

        $fileEntity = new ImportExportFileEntity();
        $fileEntity->assign($fileData);

        return $fileEntity;
    }

    public function detectType(UploadedFile $file): string
    {
        // TODO: we should do a mime type detection on the file content
        $guessedExtension = $file->guessClientExtension();
        if ($guessedExtension === 'csv' || $file->getClientOriginalExtension() === 'csv') {
            return 'text/csv';
        }

        return $file->getClientMimeType();
    }

    public function getWriter(): AbstractWriter
    {
        return $this->writer;
    }

    public function generateFilename(ImportExportProfileEntity $profile): string
    {
        $extension = $profile->getFileType() === 'text/xml' ? 'xml' : 'csv';
        $timestamp = date('Ymd-His');
        $name = $profile->getTechnicalName();

        return \sprintf('%s_%s.%s', $name, $timestamp, $extension);
    }

    /**
     * @param array<string, mixed|null> $data
     */
    public function updateFile(Context $context, string $fileId, array $data): void
    {
        $data['id'] = $fileId;
        $this->fileRepository->update([$data], $context);
    }
}
