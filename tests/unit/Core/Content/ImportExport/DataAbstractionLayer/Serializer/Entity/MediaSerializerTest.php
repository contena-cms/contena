<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\MediaSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\MediaSerializerSubscriber;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Content\Media\File\MediaFile;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Media\MediaService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(MediaSerializer::class)]
class MediaSerializerTest extends TestCase
{
    public function testExistingMediaWithSameHashDoesNotPersistDownloadedFileAgain(): void
    {
        $context = Context::createDefaultContext();
        $mediaDefinition = new MediaDefinition();
        $mediaDefinition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $mediaService = $this->createMock(MediaService::class);
        $fileSaver = $this->createMock(FileSaver::class);
        $mediaFolderRepository = static::createStub(EntityRepository::class);
        $mediaRepository = $this->createMock(EntityRepository::class);

        $mediaSerializer = new MediaSerializer($mediaService, $fileSaver, $mediaFolderRepository, $mediaRepository);
        $mediaSerializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new MediaSerializerSubscriber($mediaSerializer));

        $existingMediaId = Uuid::randomHex();
        $hash = 'existing-file-hash';

        $mediaService->expects($this->once())
            ->method('fetchFile')
            ->willReturn(new MediaFile(
                '/tmp/foo/bar/baz',
                'image/png',
                'png',
                1337,
                $hash
            ));

        $mediaRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([$existingMediaId], new Criteria(), $context));

        $fileSaver->expects($this->never())
            ->method('persistFileToMedia');

        $result = $mediaSerializer->deserialize(new Config([], [], []), $mediaDefinition, [
            'url' => 'http://172.16.11.80/contena-logo.png',
            'mediaFolderId' => Uuid::randomHex(),
        ]);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        static::assertSame($existingMediaId, $result['id']);

        $writtenResult = new EntityWriteResult($existingMediaId, $result, MediaDefinition::ENTITY_NAME, 'insert');
        $writtenEvent = new EntityWrittenEvent(MediaDefinition::ENTITY_NAME, [$writtenResult], $context);
        $eventDispatcher->dispatch($writtenEvent, 'media.written');
    }

    public function testExistingMediaWithSameIdAndHashDoesNotPersistDownloadedFileAgain(): void
    {
        $context = Context::createDefaultContext();
        $mediaDefinition = new MediaDefinition();
        $mediaDefinition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $mediaService = $this->createMock(MediaService::class);
        $fileSaver = $this->createMock(FileSaver::class);
        $mediaFolderRepository = static::createStub(EntityRepository::class);
        $mediaRepository = $this->createMock(EntityRepository::class);

        $mediaSerializer = new MediaSerializer($mediaService, $fileSaver, $mediaFolderRepository, $mediaRepository);
        $mediaSerializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new MediaSerializerSubscriber($mediaSerializer));

        $existingMediaId = Uuid::randomHex();
        $hash = 'existing-file-hash';

        $mediaEntity = new MediaEntity();
        $mediaEntity->assign([
            'id' => $existingMediaId,
            'url' => 'http://contena.test/media/generated/path/contena-logo.png',
            'metaData' => [
                'hash' => $hash,
            ],
        ]);

        $mediaRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(MediaDefinition::ENTITY_NAME, 1, new MediaCollection([$mediaEntity]), null, new Criteria(), $context));

        $mediaService->expects($this->once())
            ->method('fetchFile')
            ->willReturn(new MediaFile(
                '/tmp/foo/bar/baz',
                'image/png',
                'png',
                1337,
                $hash
            ));

        $mediaRepository->expects($this->never())
            ->method('searchIds');

        $fileSaver->expects($this->never())
            ->method('persistFileToMedia');

        $result = $mediaSerializer->deserialize(new Config([], [], []), $mediaDefinition, [
            'id' => $existingMediaId,
            'url' => 'http://contena.test/media/exported/path/contena-logo.png',
            'mediaFolderId' => Uuid::randomHex(),
        ]);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        static::assertSame($existingMediaId, $result['id']);

        $writtenResult = new EntityWriteResult($existingMediaId, $result, MediaDefinition::ENTITY_NAME, 'update');
        $writtenEvent = new EntityWrittenEvent(MediaDefinition::ENTITY_NAME, [$writtenResult], $context);
        $eventDispatcher->dispatch($writtenEvent, 'media.written');
    }
}
