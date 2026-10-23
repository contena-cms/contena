<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\File;

use League\Flysystem\FilesystemOperator;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Media\MediaException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;

class FileLoader
{
    private readonly FileNameValidator $fileNameValidator;

    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly FilesystemOperator $filesystemPublic,
        private readonly FilesystemOperator $filesystemPrivate,
        private readonly EntityRepository $mediaRepository,
        private readonly StreamFactoryInterface $streamFactory
    ) {
        $this->fileNameValidator = new FileNameValidator();
    }

    public function loadMediaFile(string $mediaId, Context $context): string
    {
        $media = $this->findMediaById($mediaId, $context);

        return $this->loadMediaEntityFile($media);
    }

    public function loadMediaFileStream(string $mediaId, Context $context): StreamInterface
    {
        $media = $this->findMediaById($mediaId, $context);

        return $this->loadMediaEntityFileStream($media);
    }

    public function loadMediaEntityFile(MediaEntity $media): string
    {
        return $this->getFileSystem($media)->read($this->getFilePath($media));
    }

    public function loadMediaEntityFileStream(MediaEntity $media): StreamInterface
    {
        $resource = $this->getFileSystem($media)->readStream($this->getFilePath($media));

        return $this->streamFactory->createStreamFromResource($resource);
    }

    private function getFilePath(MediaEntity $media): string
    {
        $this->fileNameValidator->validateFileName($media->getFileName() ?: '');

        return $media->getPath();
    }

    private function getFileSystem(MediaEntity $media): FilesystemOperator
    {
        if ($media->isPrivate()) {
            return $this->filesystemPrivate;
        }

        return $this->filesystemPublic;
    }

    /**
     * @throws MediaException
     */
    private function findMediaById(string $mediaId, Context $context): MediaEntity
    {
        $media = $this->mediaRepository->search(
            new Criteria([$mediaId]),
            $context,
        )->getEntities()->first();

        if ($media === null) {
            throw MediaException::mediaNotFound($mediaId);
        }

        return $media;
    }
}
