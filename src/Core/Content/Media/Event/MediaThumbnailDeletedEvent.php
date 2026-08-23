<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Event;

use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class MediaThumbnailDeletedEvent extends Event
{
    final public const string EVENT_NAME = 'media_thumbnail.after_delete';

    public function __construct(
        private readonly MediaThumbnailCollection $thumbnails,
        private readonly Context $context
    ) {
    }

    public function getThumbnails(): MediaThumbnailCollection
    {
        return $this->thumbnails;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
