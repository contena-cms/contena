<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Aggregate\ChannelType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeEntity;
use Contena\Core\System\Channel\Aggregate\ChannelTypeTranslation\ChannelTypeTranslationCollection;
use Contena\Core\System\Channel\ChannelCollection;

/**
 * @internal
 */
#[CoversClass(ChannelTypeEntity::class)]
class ChannelTypeEntityTest extends TestCase
{
    public function testAccessorsRoundTrip(): void
    {
        $type = new ChannelTypeEntity();

        $channels = new ChannelCollection();
        $translations = new ChannelTypeTranslationCollection();

        $type->setName('Frontend');
        $type->setManufacturer('Contena');
        $type->setDescription('Sales channel with HTML frontend');
        $type->setDescriptionLong('Sales channel with HTML frontend.');
        $type->setCoverUrl('https://example.com/cover.png');
        $type->setIconName('default-building-shop');
        $type->setScreenshotUrls(['https://example.com/screenshot.png']);
        $type->setChannels($channels);
        $type->setTranslations($translations);

        static::assertSame('Frontend', $type->getName());
        static::assertSame('Contena', $type->getManufacturer());
        static::assertSame('Sales channel with HTML frontend', $type->getDescription());
        static::assertSame('Sales channel with HTML frontend.', $type->getDescriptionLong());
        static::assertSame('https://example.com/cover.png', $type->getCoverUrl());
        static::assertSame('default-building-shop', $type->getIconName());
        static::assertSame(['https://example.com/screenshot.png'], $type->getScreenshotUrls());
        static::assertSame($channels, $type->getChannels());
        static::assertSame($translations, $type->getTranslations());
    }

    public function testUnsetOptionalValuesFallBackToEmptyValues(): void
    {
        $type = new ChannelTypeEntity();

        static::assertSame('', $type->getCoverUrl());
        static::assertSame('', $type->getIconName());
        static::assertSame([], $type->getScreenshotUrls());
    }
}
