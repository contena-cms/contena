<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Theme\CompilerConfiguration;

/**
 * @internal
 */
#[CoversClass(CompilerConfiguration::class)]
class CompilerConfigurationTest extends TestCase
{
    public function testGetNotSetValue(): void
    {
        $config = new CompilerConfiguration([]);

        static::assertNull($config->getValue('test'));
    }

    public function testGetSetValue(): void
    {
        $config = new CompilerConfiguration([
            'test' => 'value',
        ]);

        static::assertSame('value', $config->getValue('test'));
    }

    public function testGetWholeConfiguration(): void
    {
        $config = new CompilerConfiguration([
            'test' => 'value',
        ]);

        static::assertSame([
            'test' => 'value',
        ], $config->getConfiguration());
    }
}
