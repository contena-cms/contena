<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Subscriber;

use Contena\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Contena\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class MediaFolderConfigLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'media_folder_configuration.loaded' => ['unserialize', 10],
        ];
    }

    /**
     * @param EntityLoadedEvent<MediaFolderConfigurationEntity> $event
     */
    public function unserialize(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $media) {
            if ($media->getMediaThumbnailSizes() === null) {
                if ($media->getMediaThumbnailSizesRo()) {
                    /** @phpstan-ignore contena.unserializeUsage */
                    $media->setMediaThumbnailSizes(\unserialize($media->getMediaThumbnailSizesRo()));
                } else {
                    $media->setMediaThumbnailSizes(new MediaThumbnailSizeCollection());
                }
            }
        }
    }
}
