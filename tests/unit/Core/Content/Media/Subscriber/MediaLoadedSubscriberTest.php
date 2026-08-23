<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Media\MediaType\ImageType;
use Contena\Core\Content\Media\Subscriber\MediaLoadedSubscriber;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(MediaLoadedSubscriber::class)]
class MediaLoadedSubscriberTest extends TestCase
{
    private MediaLoadedSubscriber $subscriber;

    private Context $context;

    protected function setUp(): void
    {
        $this->subscriber = new MediaLoadedSubscriber();
        $this->context = Context::createDefaultContext();
    }

    public function testRestoresMediaTypeAndThumbnailsOnFullEntity(): void
    {
        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setMediaTypeRaw(serialize(new ImageType()));

        $this->subscriber->unserialize(new EntityLoadedEvent(new MediaDefinition(), [$media], $this->context));

        static::assertInstanceOf(ImageType::class, $media->getMediaType());
        static::assertInstanceOf(MediaThumbnailCollection::class, $media->getThumbnails());
        static::assertCount(0, $media->getThumbnails());
    }

    public function testRestoresMediaTypeAndThumbnailsOnPartialEntity(): void
    {
        $media = new PartialEntity();
        $media->assign([
            'id' => Uuid::randomHex(),
            'mediaTypeRaw' => serialize(new ImageType()),
        ]);

        $this->subscriber->unserialize(new PartialEntityLoadedEvent(new MediaDefinition(), [$media], $this->context));

        static::assertInstanceOf(ImageType::class, $media->get('mediaType'));
        static::assertInstanceOf(MediaThumbnailCollection::class, $media->get('thumbnails'));
        static::assertCount(0, $media->get('thumbnails'));
    }

    public function testKeepsAlreadyLoadedThumbnailsOnPartialEntity(): void
    {
        $thumbnails = new MediaThumbnailCollection();

        $media = new PartialEntity();
        $media->assign([
            'id' => Uuid::randomHex(),
            'thumbnails' => $thumbnails,
        ]);

        $this->subscriber->unserialize(new PartialEntityLoadedEvent(new MediaDefinition(), [$media], $this->context));

        static::assertSame($thumbnails, $media->get('thumbnails'));
    }

    public function testDoesNotSetMediaTypeWhenRawIsMissingOnPartialEntity(): void
    {
        $media = new PartialEntity();
        $media->assign(['id' => Uuid::randomHex()]);

        $this->subscriber->unserialize(new PartialEntityLoadedEvent(new MediaDefinition(), [$media], $this->context));

        static::assertFalse($media->has('mediaType'));
        static::assertInstanceOf(MediaThumbnailCollection::class, $media->get('thumbnails'));
    }
}
