<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\Channel\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Channel\Struct\ImageStruct;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(ImageStruct::class)]
class ImageStructTest extends TestCase
{
    public function testApiAlias(): void
    {
        static::assertSame('cms_image', (new ImageStruct())->getApiAlias());
    }

    public function testMediaAccessors(): void
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $struct = new ImageStruct();
        $struct->setMedia($media);
        $struct->setMediaId('media123');

        static::assertSame($media, $struct->getMedia());
        static::assertSame('media123', $struct->getMediaId());
    }

    public function testUrlAccessors(): void
    {
        $struct = new ImageStruct();
        $struct->setUrl('http://contena.cn/image.jpg');

        static::assertSame('http://contena.cn/image.jpg', $struct->getUrl());
    }

    public function testAriaLabelAccessors(): void
    {
        $struct = new ImageStruct();
        $struct->setAriaLabel('decorative image');

        static::assertSame('decorative image', $struct->getAriaLabel());
    }

    public function testNewTabAccessors(): void
    {
        $struct = new ImageStruct();
        $struct->setNewTab(true);

        static::assertTrue($struct->getNewTab());
    }
}
