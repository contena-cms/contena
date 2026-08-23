<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Event\MediaUploadedEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(MediaUploadedEvent::class)]
class MediaUploadedEventTest extends TestCase
{
    public function testInstance(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $mediaUploadEvent = new MediaUploadedEvent(
            $mediaId,
            $context
        );

        static::assertSame('media.uploaded', $mediaUploadEvent->getName());
        static::assertSame($mediaId, $mediaUploadEvent->getMediaId());
        static::assertSame(['mediaId' => $mediaId], $mediaUploadEvent->getValues());
        static::assertArrayHasKey('mediaId', MediaUploadedEvent::getAvailableData()->toArray());
        static::assertSame(
            $context,
            $mediaUploadEvent->getContext()
        );
    }
}
