<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\Channel\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Channel\Struct\VideoStruct;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(VideoStruct::class)]
class VideoStructTest extends TestCase
{
    public function testApiAlias(): void
    {
        static::assertSame('cms_video', (new VideoStruct())->getApiAlias());
    }

    public function testInheritedAccessors(): void
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $struct = new VideoStruct();
        $struct->setMedia($media);
        $struct->setMediaId('media123');
        $struct->setAriaLabel('demo');

        static::assertSame($media, $struct->getMedia());
        static::assertSame('media123', $struct->getMediaId());
        static::assertSame('demo', $struct->getAriaLabel());
    }
}
