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
            'default' => ['key' => 'value'],
        ];

        $service = new SymfonySystemConfigService($config);

        static::assertSame($config['default'], $service->getConfig());
    }

    public function testGet(): void
    {
        $config = [
            'default' => ['key' => 'value'],
        ];

        $service = new SymfonySystemConfigService($config);

        static::assertSame('value', $service->get('key'));
        static::assertTrue($service->has('key'));
        static::assertFalse($service->has('nonExistentKey'));
        static::assertNull($service->get('nonExistentKey'));
    }

    public function testHas(): void
    {
        $config = [
            'default' => ['key' => 'value'],
        ];

        $service = new SymfonySystemConfigService($config);

        static::assertTrue($service->has('key'));
        static::assertFalse($service->has('nonExistentKey'));
    }

    public function testOverride(): void
    {
        $config = [
            'default' => ['key' => 'value', 'onlyDefault' => 'value'],
        ];

        $service = new SymfonySystemConfigService($config);

        $merged = [
            'key' => null,
        ];

        static::assertSame(['key' => 'value', 'onlyDefault' => 'value'], $service->override($merged, null));
    }

    public function testOverrideNested(): void
    {
        $config = [
            'default' => ['key' => 'value', 'nested.key' => 'value', 'first.key' => 'test'],
        ];

        $service = new SymfonySystemConfigService($config);

        $merged = [
            'key' => null,
            'nested' => [
                'key' => null,
            ],
        ];

        static::assertSame(['key' => 'value', 'nested' => ['key' => 'value'], 'first' => ['key' => 'test']], $service->override($merged, null));
    }
}
