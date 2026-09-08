<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Aggregate\MediaThumbnail;

use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MediaThumbnailEntity::class)]
class MediaThumbnailStructTest extends TestCase
{
    public function testGetIdentifier(): void
    {
        $thumbnail = new MediaThumbnailEntity();
        $thumbnail->setWidth(120);
        $thumbnail->setHeight(100);

        static::assertSame('120x100', $thumbnail->getIdentifier());
    }
}
