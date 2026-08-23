<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Frontend\Theme\CompilerConfiguration;
use Contena\Frontend\Theme\ScssPhpCompiler;

/**
 * @internal
 */
class ThemeCompilerPluginConfigurationTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCompilesWithPluginScssVariables(): void
    {
        $testScss = <<<'SCSS'
.test-selector-plugin {
    background: $simple-plugin-backgroundcolor;
    color: $simple-plugin-fontcolor;
}
SCSS;

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([]),
            '$simple-plugin-backgroundcolor: #ffffff; $simple-plugin-fontcolor: #000000; ' . $testScss
        );

        static::assertStringContainsString('.test-selector-plugin', $result);
        static::assertStringContainsString('#ffffff', $result);
        static::assertStringContainsString('#000000', $result);
    }

    public function testCompilesWithAppScssVariables(): void
    {
        $testScss = <<<'SCSS'
.test-selector-app {
    background: $no-theme-custom-css-backgroundcolor;
    color: $no-theme-custom-css-fontcolor;
}
SCSS;

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([]),
            '$no-theme-custom-css-backgroundcolor: #aabbcc; $no-theme-custom-css-fontcolor: #ddeeff; ' . $testScss
        );

        static::assertStringContainsString('.test-selector-app', $result);
        static::assertStringContainsString('#aabbcc', $result);
        static::assertStringContainsString('#ddeeff', $result);
    }

    public function testCompilesPluginAndAppCssWithNullValueHandling(): void
    {
        $testScss = <<<'SCSS'
.test-selector-plugin {
    background: $simple-plugin-backgroundcolor;
    color: $simple-plugin-fontcolor;
    border: $simple-plugin-bordercolor;
}
.test-selector-app {
    background: $no-theme-custom-css-backgroundcolor;
    color: $no-theme-custom-css-fontcolor;
    border: $no-theme-custom-css-bordercolor;
}
SCSS;

        // Build variables with nulls - border variables intentionally null.
        $variables = '$simple-plugin-backgroundcolor: #fff; ';
        $variables .= '$simple-plugin-fontcolor: #eee; ';
        $variables .= '$simple-plugin-bordercolor: null; ';
        $variables .= '$no-theme-custom-css-backgroundcolor: #aaa; ';
        $variables .= '$no-theme-custom-css-fontcolor: #eee; ';
        $variables .= '$no-theme-custom-css-bordercolor: null; ';

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([]),
            $variables . $testScss
        );

        static::assertStringContainsString('.test-selector-plugin', $result);
        static::assertStringContainsString('background:#fff', str_replace(' ', '', $result));
        static::assertStringContainsString('color:#eee', str_replace(' ', '', $result));

        static::assertStringContainsString('.test-selector-app', $result);
        static::assertStringContainsString('background:#aaa', str_replace(' ', '', $result));

        $normalizedResult = str_replace([' ', "\n", "\r"], '', strtolower($result));
        static::assertStringNotContainsString(
            'border:',
            $normalizedResult,
            'Border properties should be omitted when variable value is null'
        );
    }
}
