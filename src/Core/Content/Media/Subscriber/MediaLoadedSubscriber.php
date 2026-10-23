<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Subscriber;

use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;

class MediaLoadedSubscriber
{
    /**
     * Accessed via generic entity accessors so it can serve both fully hydrated `MediaEntity`
     * instances (`media.loaded`) and `PartialEntity` instances from partial loading
     * (`media.partial_loaded`), which do not expose the typed media getters/setters.
     *
     * @param EntityLoadedEvent<MediaEntity>|PartialEntityLoadedEvent $event
     */
    public function unserialize(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $media) {
            $mediaTypeRaw = $media->has('mediaTypeRaw') ? $media->get('mediaTypeRaw') : null;

            if ($mediaTypeRaw) {
                /** @phpstan-ignore contena.unserializeUsage */
                $media->assign(['mediaType' => \unserialize($mediaTypeRaw)]);
            }

            if (($media->has('thumbnails') ? $media->get('thumbnails') : null) !== null) {
                continue;
            }

            $thumbnailsRo = $media->has('thumbnailsRo') ? $media->get('thumbnailsRo') : null;

            $thumbnails = match (true) {
                /** @phpstan-ignore contena.unserializeUsage */
                $thumbnailsRo !== null => \unserialize($thumbnailsRo),
                default => new MediaThumbnailCollection(),
            };

            $media->assign(['thumbnails' => $thumbnails]);
        }
    }
}
