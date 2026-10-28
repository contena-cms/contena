<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Contena\Core\Content\ImportExport\Service\FileService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class FileServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @param array<string, string> $fileData
     */
    #[DataProvider('fileTypesProvider')]
    public function testDetectType(array $fileData): void
    {
        $fileService = new FileService(
            static::getContainer()->get('contena.filesystem.private'),
            static::getContainer()->get('import_export_file.repository')
        );

        $filePath = $fileData['file'];
        $file = fopen($filePath, 'w');
        static::assertIsResource($file);
        fwrite($file, (string) $fileData['content']);
        fclose($file);

        $uploadedFile = new UploadedFile($filePath, $filePath, $fileData['providedType']);

        $detectedType = $fileService->detectType($uploadedFile);
        static::assertSame($fileData['expectedType'], $detectedType);

        unlink($filePath);
    }

    public function testStoreFile(): void
    {
        /** @var EntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository */
        $fileRepository = static::getContainer()->get('import_export_file.repository');
        $fileService = new FileService(
            static::getContainer()->get('contena.filesystem.private'),
            $fileRepository
        );

        $storedFile = $fileService->storeFile(
            Context::createDefaultContext(),
            new \DateTimeImmutable(),
            null,
            'testfile.csv',
            ImportExportLogEntity::ACTIVITY_IMPORT
        );

        static::assertSame('testfile.csv', $storedFile->getOriginalName());

        $dbFile = $fileRepository->search(new Criteria([$storedFile->getId()]), Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(ImportExportFileEntity::class, $dbFile);
        static::assertSame('testfile.csv', $dbFile->getOriginalName());
    }

    public static function fileTypesProvider(): \Generator
    {
        yield 'CSV file with correct type' => [
            [
                'file' => 'testfile.csv',
                'content' => 'asdf;jkl;wer;\r\n',
                'providedType' => 'text/csv',
                'expectedType' => 'text/csv',
            ],
        ];
        yield 'CSV file with plain type' => [
            [
                'file' => 'testfile.csv',
                'content' => 'asdf;jkl;wer;\r\n',
                'providedType' => 'text/plain',
                'expectedType' => 'text/csv',
            ],
        ];
        yield 'Txt file with plain type' => [
            [
                'file' => 'testfile.txt',
                'content' => 'some text\r\n',
                'providedType' => 'text/plain',
                'expectedType' => 'text/plain',
            ],
        ];
        yield '' => [
            [
                'file' => 'testfile.json',
                'content' => '{}\r\n',
                'providedType' => 'application/json',
                'expectedType' => 'application/json',
            ],
        ];
    }
}
