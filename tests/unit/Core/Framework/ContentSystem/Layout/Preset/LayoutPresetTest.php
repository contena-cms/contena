<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset;

use Contena\Core\Framework\ContentSystem\Layout\Preset\LayoutPreset;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LayoutPreset::class)]
class LayoutPresetTest extends TestCase
{
    #[TestDox('toArray exposes every field under its wire key')]
    public function testToArrayExposesAllFields(): void
    {
        $payload = [
            ['id' => 'el-1', 'component' => 'Ct:Content:Text', 'properties' => ['text' => '<p>Hi</p>']],
        ];

        $preset = new LayoutPreset('core.text-block', 'Text block', 'A single text element.', 'regular-align-left', $payload);

        static::assertSame([
            'id' => 'core.text-block',
            'name' => 'Text block',
            'description' => 'A single text element.',
            'icon' => 'regular-align-left',
            'payload' => $payload,
        ], $preset->toArray());
    }

    #[TestDox('toArray keeps null description and icon')]
    public function testToArrayKeepsNullMetadata(): void
    {
        $preset = new LayoutPreset('core.empty', 'Empty', null, null, []);

        $result = $preset->toArray();

        static::assertNull($result['description']);
        static::assertNull($result['icon']);
        static::assertSame([], $result['payload']);
    }
}
