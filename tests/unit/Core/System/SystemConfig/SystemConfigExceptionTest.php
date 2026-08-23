<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SystemConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\SystemConfig\SystemConfigException;

/**
 * @internal
 */
#[CoversClass(SystemConfigException::class)]
class SystemConfigExceptionTest extends TestCase
{
    public function testSystemConfigKeyIsManagedBySystems(): void
    {
        $exception = SystemConfigException::systemConfigKeyIsManagedBySystems('configKey');

        static::assertSame('The system configuration key "configKey" cannot be changed, as it is managed by the Contena yaml file configuration system provided by Symfony.', $exception->getMessage());
        static::assertSame('configKey', $exception->getParameters()['configKey']);
    }

    public function testInvalidDomainException(): void
    {
        $exception = SystemConfigException::invalidDomain('domain');

        static::assertSame('Invalid domain \'domain\'', $exception->getMessage());
        static::assertSame('domain', $exception->getParameters()['domain']);
    }

    public function testInvalidKeyException(): void
    {
        $exception = SystemConfigException::invalidKey('key');

        static::assertSame('Invalid key \'key\'', $exception->getMessage());
        static::assertSame('key', $exception->getParameters()['key']);
    }

    public function testInvalidSettingValueException(): void
    {
        $exception = SystemConfigException::invalidSettingValueException('key', 'type', 'value');

        static::assertSame('Invalid setting value for key "key". Expected type "type", got "value".', $exception->getMessage());
        static::assertSame('key', $exception->getParameters()['key']);
        static::assertSame('type', $exception->getParameters()['expectedType']);
        static::assertSame('value', $exception->getParameters()['actualType']);
    }
}
