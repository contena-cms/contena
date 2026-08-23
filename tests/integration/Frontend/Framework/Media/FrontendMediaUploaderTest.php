<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Media;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Content\Media\MediaException;
use Contena\Core\Content\Media\MediaService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Frontend\Framework\FrontendFrameworkException;
use Contena\Frontend\Framework\Media\FrontendMediaUploader;
use Contena\Frontend\Framework\Media\FrontendMediaValidatorRegistry;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 */
class FrontendMediaUploaderTest extends TestCase
{
    use KernelTestBehaviour;

    final public const string FIXTURE_DIR = __DIR__ . '/fixtures';

    public function testUploadDocument(): void
    {
        $file = $this->getUploadFixture('empty.pdf');
        $result = $this->getUploadService()->upload($file, 'test', 'documents', Context::createDefaultContext());

        $repo = static::getContainer()->get('media.repository');
        static::assertSame(1, $repo->search(new Criteria([$result]), Context::createDefaultContext())->getTotal());
        $this->removeMedia($result);
    }

    public function testUploadDocumentFailIllegalFileType(): void
    {
        $this->expectExceptionObject(FrontendFrameworkException::fileTypeNotAllowed('application/vnd.ms-excel', 'documents'));

        $file = $this->getUploadFixture('empty.xls');
        $this->getUploadService()->upload($file, 'test', 'documents', Context::createDefaultContext());
    }

    public function testUploadDocumentFailFilenameContainsPhp(): void
    {
        $this->expectExceptionObject(MediaException::illegalFileName('contains.php.pdf', 'contains PHP related file extension'));

        $file = $this->getUploadFixture('contains.php.pdf');
        $this->getUploadService()->upload($file, 'test', 'documents', Context::createDefaultContext());
    }

    public function testUploadImage(): void
    {
        $file = $this->getUploadFixture('image.png');
        $result = $this->getUploadService()->upload($file, 'test', 'images', Context::createDefaultContext());

        $repo = static::getContainer()->get('media.repository');
        static::assertSame(1, $repo->search(new Criteria([$result]), Context::createDefaultContext())->getTotal());
        $this->removeMedia($result);
    }

    public function testUploadDocumentFailIllegalImageType(): void
    {
        $this->expectExceptionObject(FrontendFrameworkException::fileTypeNotAllowed('image/webp', 'images'));

        $file = $this->getUploadFixture('image.webp');
        $this->getUploadService()->upload($file, 'test', 'images', Context::createDefaultContext());
    }

    public function testUploadUnknownType(): void
    {
        $this->expectExceptionObject(FrontendFrameworkException::mediaValidatorMissing('notExistingType'));

        $file = $this->getUploadFixture('image.png');
        $this->getUploadService()->upload($file, 'test', 'notExistingType', Context::createDefaultContext());
    }

    private function getUploadFixture(string $filename): UploadedFile
    {
        return new UploadedFile(self::FIXTURE_DIR . '/' . $filename, $filename, null, null, true);
    }

    private function getUploadService(): FrontendMediaUploader
    {
        return new FrontendMediaUploader(
            static::getContainer()->get(MediaService::class),
            static::getContainer()->get(FileSaver::class),
            static::getContainer()->get(FrontendMediaValidatorRegistry::class),
        );
    }

    private function removeMedia(string $id): void
    {
        static::getContainer()->get('media.repository')->delete(
            [['id' => $id]],
            Context::createDefaultContext(),
        );
    }
}
