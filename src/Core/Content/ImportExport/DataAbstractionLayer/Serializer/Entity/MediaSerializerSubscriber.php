<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Contena\Core\Content\Media\MediaEvents;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class MediaSerializerSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AbstractMediaSerializer $mediaSerializer)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MediaEvents::MEDIA_WRITTEN_EVENT => 'forward',
        ];
    }

    public function forward(EntityWrittenEvent $event): void
    {
        $this->mediaSerializer->persistMedia($event);
    }
}
