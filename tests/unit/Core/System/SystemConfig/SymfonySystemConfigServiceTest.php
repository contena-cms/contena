<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SystemConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\SystemConfig\SymfonySystemConfigService;

/**
 * @internal
 */
#[CoversClass(SymfonySystemConfigService::class)]
class SymfonySystemConfigServiceTest extends TestCase
{
    public function testGetConfig(): void
    {
        $config = [
            'default' => [
                'key' => 'value',
            ],
            'channelId' => [
                'key' => 'value',
            ],
        ];

        $service = new SymfonySystemConfigService($config);

        static::assertSame($config['default'], $service->getConfig());
        static::assertSame($config['channelId'], $service->getConfig('channelId'));
    }

    public function testGet(): void
    {
        $config = [
            'default' => [
                'key' => 'value',
            ],
            'channelId' => [
                'key' => 'value2',
            ],
        ];

        $service = new SymfonySystemConfigService($config);

        static::assertSame('value', $service->get('key'));
        static::assertSame('value2', $service->get('key', 'channelId'));
        static::assertTrue($service->has('key'));
        static::assertFalse($service->has('nonExistentKey'));
        static::assertNull($service->get('nonExistentKey'));
        static::assertNull($service->get('nonExistentKey', 'channelId'));
    }

    public function testHas(): void
    {
        $config = [
            'default' => [
                'key' => 'value',
            ],
            'channelId' => [
                'key' => 'value',
            ],
        ];

        $service = new SymfonySystemConfigService($config);

        static::assertTrue($service->has('key'));
        static::assertFalse($service->has('nonExistentKey'));
    }

    public function testOverride(): void
    {
        $config = [
            'default' => [
                'key' => 'value',
                'onlyDefault' => 'value',
            ],
            'channelId' => [
                'key' => 'value2',
            ],
        ];

        $service = new SymfonySystemConfigService($config);

        $merged = [
            'key' => null,
        ];

        static::assertSame(['key' => 'value', 'onlyDefault' => 'value'], $service->override($merged, null, inherit: false, nesting: false));
        static::assertSame(['key' => 'value2'], $service->override($merged, 'channelId', inherit: false, nesting: false));
        static::assertSame(['key' => 'value2', 'onlyDefault' => 'value'], $service->override($merged, 'channelId', nesting: false));
    }

    public function testOverrideNested(): void
    {
        $config = [
            'default' => [
                'key' => 'value',
                'nested.key' => 'value',
                'first.key' => 'test',
            ],
            'channelId' => [
                'key' => 'value2',
                'nested.key' => 'value2',
                'second.key' => 'test',
            ],
        ];

        $service = new SymfonySystemConfigService($config);

        $merged = [
            'key' => null,
            'nested' => [
                'key' => null,
            ],
        ];

        static::assertSame(['key' => 'value', 'nested' => ['key' => 'value'], 'first' => ['key' => 'test']], $service->override($merged, null, inherit: false));
        static::assertSame(['key' => 'value2', 'nested' => ['key' => 'value2'], 'second' => ['key' => 'test']], $service->override($merged, 'channelId', inherit: false));
        static::assertSame(['key' => 'value2', 'nested' => ['key' => 'value2'], 'first' => ['key' => 'test'], 'second' => ['key' => 'test']], $service->override($merged, 'channelId'));
    }

    public function testOverrideNestedKeepsEmptyArrayChannelValue(): void
    {
        $service = new SymfonySystemConfigService([
            'default' => ['key' => ['global-id']],
            'channelId' => ['key' => []],
        ]);

        static::assertSame(['key' => []], $service->override([], 'channelId'));
    }

    public function testEmptyArrayStaticOverrideReplacesExistingArray(): void
    {
        $service = new SymfonySystemConfigService([
            'default' => ['foo.bar' => ['global-id']],
            'channelId' => ['foo.bar' => []],
        ]);

        static::assertSame(
            ['foo' => ['bar' => [], 'other' => 'unchanged']],
            $service->override(['foo' => ['bar' => ['database-id'], 'other' => 'unchanged']], 'channelId')
        );
        static::assertSame(
            ['foo.bar' => []],
            $service->override(['foo.bar' => ['database-id']], 'channelId', nesting: false)
        );
    }

    public function testNonEmptyStaticArrayKeepsExistingEntries(): void
    {
        $service = new SymfonySystemConfigService([
            'default' => ['foo.bar' => ['static' => 'value']],
        ]);

        static::assertSame(
            ['foo' => ['bar' => ['database' => 'value', 'static' => 'value']]],
            $service->override(['foo' => ['bar' => ['database' => 'value']]], null)
        );
        static::assertSame(
            ['foo.bar' => ['database' => 'value', 'static' => 'value']],
            $service->override(['foo.bar' => ['database' => 'value']], null, nesting: false)
        );
    }

    public function testChannelDescendantOverridesEmptyDefaultParent(): void
    {
        $service = new SymfonySystemConfigService([
            'default' => ['foo.bar' => []],
            'channelId' => ['foo.bar.baz' => 'sales'],
        ]);

        static::assertSame(
            ['foo' => ['bar' => ['baz' => 'sales']]],
            $service->override(['foo' => ['bar' => ['database' => 'value']]], 'channelId')
        );
    }

    public function testEmptyChannelParentOverridesDefaultDescendant(): void
    {
        $service = new SymfonySystemConfigService([
            'default' => ['foo.bar.baz' => 'default'],
            'channelId' => ['foo.bar' => []],
        ]);

        static::assertSame(
            ['foo' => ['bar' => []]],
            $service->override([], 'channelId')
        );
    }
}
