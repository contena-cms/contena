<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;

/**
 * @internal
 */
#[CoversClass(PluginFromFileSystemStruct::class)]
class PluginFromFileSystemStructTest extends TestCase
{
    #[DataProvider('dataProviderTestGetName')]
    public function testGetName(PluginFromFileSystemStruct $pluginFromFileSystem, string $expectedResult): void
    {
        static::assertSame($expectedResult, $pluginFromFileSystem->getName());
    }

    /**
     * @return list<array{PluginFromFileSystemStruct, string}>
     */
    public static function dataProviderTestGetName(): array
    {
        return [
            [
                self::getPluginFromFileSystemStructWithBaseClass('CtFoo\\CtFoo'),
                'CtFoo',
            ],
            [
                self::getPluginFromFileSystemStructWithBaseClass('Ct\\PayPal\\CtPayPal\\CtPayPalExtension'),
                'CtPayPalExtension',
            ],
            [
                self::getPluginFromFileSystemStructWithBaseClass('//Ct\\PayPal\\CtPay/Pal\\CtPayPal-Extension'),
                'CtPayPal-Extension',
            ],
            [
                self::getPluginFromFileSystemStructWithBaseClass('Test'),
                'Test',
            ],
        ];
    }

    private static function getPluginFromFileSystemStructWithBaseClass(string $baseClass): PluginFromFileSystemStruct
    {
        return new PluginFromFileSystemStruct()->assign([
            'baseClass' => $baseClass,
        ]);
    }
}
