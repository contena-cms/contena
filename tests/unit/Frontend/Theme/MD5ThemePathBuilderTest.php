<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Theme\MD5ThemePathBuilder;

/**
 * @internal
 */
#[CoversClass(MD5ThemePathBuilder::class)]
class MD5ThemePathBuilderTest extends TestCase
{
    public function testAssemblePath(): void
    {
        $builder = new MD5ThemePathBuilder();
        $path = $builder->assemblePath('channelId', 'themeId');

        static::assertSame('5c7a1cfde64c7f4533daa5a0c06c0a39', $path);
    }

    public function testGenerateNewPathEqualsAssemblePath(): void
    {
        $builder = new MD5ThemePathBuilder();
        $path = $builder->generateNewPath('channelId', 'themeId', 'foo');

        static::assertSame($builder->assemblePath('channelId', 'themeId'), $path);
    }

    public function testGenerateNewPathEqualsIgnoresSeed(): void
    {
        $builder = new MD5ThemePathBuilder();

        static::assertSame(
            $builder->generateNewPath('channelId', 'themeId', 'foo'),
            $builder->generateNewPath('channelId', 'themeId', 'bar')
        );
    }
}
