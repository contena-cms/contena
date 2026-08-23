<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Upload;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Contena\Core\Content\Media\Thumbnail\ThumbnailService;
use Contena\Core\Framework\Context;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * Handles physical file operations during media replace and post-upload processing:
 * deleting old files, cleaning up thumbnails, and dispatching thumbnail generation.
 */
readonly class MediaFileCleanupService
{
    public function __construct(
        private FilesystemOperator $filesystemPublic,
        private FilesystemOperator $filesystemPrivate,
        private ThumbnailService $thumbnailService,
        private MessageBusInterface $messageBus,
        private bool $remoteThumbnailsEnable,
    ) {
    }

    public function removeOldMediaData(MediaEntity $media, Context $context): void
    {
        if (!$media->hasFile()) {
            return;
        }

        $filesystem = $media->isPrivate() ? $this->filesystemPrivate : $this->filesystemPublic;

        try {
            $filesystem->delete($media->getPath());
        } catch (UnableToDeleteFile) {
        }

        $this->deleteThumbnails($media, $context);
    }

    public function deleteThumbnails(MediaEntity $media, Context $context): void
    {
        if ($this->remoteThumbnailsEnable) {
            return;
        }

        $this->thumbnailService->deleteThumbnails($media, $context);
    }

    public function dispatchThumbnailGeneration(string $mediaId, Context $context): void
    {
        if ($this->remoteThumbnailsEnable) {
            return;
        }

        $message = new GenerateThumbnailsMessage();
        $message->setMediaIds([$mediaId]);
        $message->setContext($context);

        $this->messageBus->dispatch($message);
    }
}
