<?php
declare(strict_types=1);

namespace Contena\Tests\DevOps\Core;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;

/**
 * @internal
 */
class DefaultsTest extends TestCase
{
    private const int CURRENT_AMOUNT_OF_DEFAULT_CONSTANTS = 5;

    public function testValues(): void
    {
        $defaults = new \ReflectionClass(Defaults::class);
        static::assertCount(self::CURRENT_AMOUNT_OF_DEFAULT_CONSTANTS, $defaults->getConstants(), 'Ensure, that every default value is checked here');

        static::assertSame('2fbb5fe2e29a4d70aa5854ce7ce3e20b', Defaults::LANGUAGE_SYSTEM);
        static::assertSame('0fa91ce3e96a4bc2be4bd9ce752c3425', Defaults::LIVE_VERSION);
        static::assertSame('Y-m-d H:i:s.v', Defaults::STORAGE_DATE_TIME_FORMAT);
        static::assertSame('Y-m-d', Defaults::STORAGE_DATE_FORMAT);
        static::assertSame('U.u', Defaults::MICROTIME_FORMAT);
    }
}
