<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Test\Generator;
use Contena\Frontend\Framework\Twig\TemplateConfigAccessor;
use Contena\Frontend\Theme\ThemeConfigValueAccessor;
use Contena\Frontend\Theme\ThemeScripts;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * @internal
 */
#[CoversClass(TemplateConfigAccessor::class)]
class TemplateConfigAccessorTest extends TestCase
{
    private ThemeConfigValueAccessor&Stub $themeConfigAccessor;

    private ThemeScripts&Stub $themeScripts;

    private TemplateConfigAccessor $accessor;

    protected function setUp(): void
    {
        $this->themeConfigAccessor = static::createStub(ThemeConfigValueAccessor::class);
        $this->themeScripts = static::createStub(ThemeScripts::class);
        $this->accessor = new TemplateConfigAccessor(
            $this->themeConfigAccessor,
            $this->themeScripts,
            'prod',
            [],
        );
    }

    public function testScriptsDelegatesToThemeScripts(): void
    {
        $this->themeScripts->method('getThemeScripts')->willReturn([
            'js/frontend/frontend.js',
            'js/app.js',
        ]);

        static::assertSame(['js/frontend/frontend.js', 'js/app.js'], $this->accessor->scripts());
    }

    public function testScriptsReturnsEmptyArrayWhenNoScripts(): void
    {
        $this->themeScripts->method('getThemeScripts')->willReturn([]);

        static::assertSame([], $this->accessor->scripts());
    }

    public function testImportMapResolvesStoredRelativeMapWithAssetPackage(): void
    {
        $storedMap = [
            'imports' => [
                'contena' => '/bundles/frontend/frontend/contena/contena.js',
                'CT:Button' => '/bundles/frontend/frontend/components/CT/Button.js',
                'CT:Blog:Card' => '/bundles/frontend/frontend/components/CT/Blog/Card.js',
            ],
        ];

        $this->themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = new TemplateConfigAccessor(
            $this->themeConfigAccessor,
            $this->themeScripts,
            'prod',
            ['asset' => new UrlPackage('https://cdn.example.com', new EmptyVersionStrategy())],
        );
        $result = $accessor->importMap();

        static::assertSame(
            [
                'imports' => [
                    'contena' => 'https://cdn.example.com/bundles/frontend/frontend/contena/contena.js',
                    'CT:Button' => 'https://cdn.example.com/bundles/frontend/frontend/components/CT/Button.js',
                    'CT:Blog:Card' => 'https://cdn.example.com/bundles/frontend/frontend/components/CT/Blog/Card.js',
                ],
            ],
            $result
        );
    }

    public function testImportMapReturnsScopesFromStoredMap(): void
    {
        $storedMap = [
            'imports' => [
                'contena' => '/bundles/frontend/frontend/contena/contena.js',
                'debounce' => '/bundles/myplugin/frontend/components/vendor/debounce-abc123.js',
                'MyPlugin:Wusel:Counter' => '/bundles/myplugin/frontend/components/MyPlugin/Wusel/Counter.js',
            ],
            'scopes' => [
                '/bundles/myplugin/frontend/components/MyPlugin/' => [
                    'debounce' => '/bundles/myplugin/frontend/components/vendor/debounce-abc123.js',
                ],
            ],
        ];

        $this->themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = new TemplateConfigAccessor(
            $this->themeConfigAccessor,
            $this->themeScripts,
            'prod',
            ['asset' => new UrlPackage('https://cdn.example.com', new EmptyVersionStrategy())],
        );
        $result = $accessor->importMap();

        static::assertSame(
            [
                'imports' => [
                    'contena' => 'https://cdn.example.com/bundles/frontend/frontend/contena/contena.js',
                    'debounce' => 'https://cdn.example.com/bundles/myplugin/frontend/components/vendor/debounce-abc123.js',
                    'MyPlugin:Wusel:Counter' => 'https://cdn.example.com/bundles/myplugin/frontend/components/MyPlugin/Wusel/Counter.js',
                ],
                'scopes' => [
                    'https://cdn.example.com/bundles/myplugin/frontend/components/MyPlugin/' => [
                        'debounce' => 'https://cdn.example.com/bundles/myplugin/frontend/components/vendor/debounce-abc123.js',
                    ],
                ],
            ],
            $result
        );
    }

    public function testImportMapResolvesStylesWithAssetPackage(): void
    {
        $storedMap = [
            'imports' => [
                'contena' => '/bundles/frontend/frontend/contena/contena.js',
            ],
            'styles' => [
                '/bundles/frontend/frontend/components/CT/Button.css',
                '/bundles/myplugin/frontend/components/MyPlugin/Wusel/Counter.css',
            ],
        ];

        $this->themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = new TemplateConfigAccessor(
            $this->themeConfigAccessor,
            $this->themeScripts,
            'prod',
            ['asset' => new UrlPackage('https://cdn.example.com', new EmptyVersionStrategy())],
        );

        static::assertSame(
            [
                'imports' => [
                    'contena' => 'https://cdn.example.com/bundles/frontend/frontend/contena/contena.js',
                ],
                'styles' => [
                    'https://cdn.example.com/bundles/frontend/frontend/components/CT/Button.css',
                    'https://cdn.example.com/bundles/myplugin/frontend/components/MyPlugin/Wusel/Counter.css',
                ],
            ],
            $accessor->importMap()
        );
    }

    public function testImportMapKeepsNonImportKeysUnchangedWithoutAssetPackage(): void
    {
        $storedMap = [
            'imports' => [
                'contena' => '/bundles/frontend/frontend/contena/contena.js',
            ],
            'scopes' => [
                '/bundles/myplugin/frontend/components/MyPlugin/' => [
                    'debounce' => '/bundles/myplugin/frontend/components/vendor/debounce-abc123.js',
                ],
            ],
            'styles' => [
                '/bundles/frontend/frontend/components/CT/Button.css',
            ],
            'scripts' => [
                '/bundles/frontend/frontend/components/CT/Button.js',
            ],
            'themeId' => 'theme-123',
        ];

        $this->themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = new TemplateConfigAccessor(
            $this->themeConfigAccessor,
            $this->themeScripts,
            'prod',
            [],
        );

        static::assertSame($storedMap, $accessor->importMap());
    }

    public function testImportMapStripsQueryFromResolvedScopeKeys(): void
    {
        $storedMap = [
            'imports' => [
                '@ext/vendor' => '/bundles/myextension/frontend/components/vendor/ext-HASH.js',
            ],
            'scopes' => [
                '/bundles/myextension/frontend/components/MyExtension/' => [
                    '@ext/vendor' => '/bundles/myextension/frontend/components/vendor/ext-HASH.js',
                ],
            ],
        ];

        $this->themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = new TemplateConfigAccessor(
            $this->themeConfigAccessor,
            $this->themeScripts,
            'prod',
            [
                'asset' => new UrlPackage(
                    'https://cdn.example.com/base',
                    new class implements VersionStrategyInterface {
                        public function getVersion(string $path): string
                        {
                            return 'v123';
                        }

                        public function applyVersion(string $path): string
                        {
                            return $path . '?v123';
                        }
                    }
                ),
            ],
        );

        static::assertSame(
            [
                'imports' => [
                    '@ext/vendor' => 'https://cdn.example.com/base/bundles/myextension/frontend/components/vendor/ext-HASH.js?v123',
                ],
                'scopes' => [
                    'https://cdn.example.com/base/bundles/myextension/frontend/components/MyExtension/' => [
                        '@ext/vendor' => 'https://cdn.example.com/base/bundles/myextension/frontend/components/vendor/ext-HASH.js?v123',
                    ],
                ],
            ],
            $accessor->importMap()
        );
    }

    public function testImportMapReturnsEmptyImportsWhenNoBuildPresent(): void
    {
        $this->themeScripts->method('getImportMap')->willReturn(null);
        $result = $this->accessor->importMap();

        static::assertSame(['imports' => []], $result);
    }

    public function testThemeDelegatesToThemeConfigAccessor(): void
    {
        $context = Generator::generateChannelContext();

        $themeConfigAccessor = $this->createMock(ThemeConfigValueAccessor::class);
        $themeConfigAccessor->expects($this->once())
            ->method('get')
            ->with('my-theme-key', $context, 'theme-id-123')
            ->willReturn('#ff0000');

        $accessor = $this->createAccessor(themeConfigAccessor: $themeConfigAccessor);

        static::assertSame('#ff0000', $accessor->theme('my-theme-key', $context, 'theme-id-123'));
    }

    public function testImportMapPrefersDevImportMapWhenDevEnvAndFlagFilePresent(): void
    {
        $devMap = [
            'imports' => ['contena' => 'http://localhost:5176/src/contena.ts'],
            'styles' => ['http://localhost:5176/@fs/foo.scss'],
        ];

        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getDevImportMap')->willReturn($devMap);
        $themeScripts->expects($this->never())->method('getImportMap');
        $accessor = $this->createAccessor(themeScripts: $themeScripts, env: 'dev');

        $result = $accessor->importMap();

        static::assertSame(
            [
                'imports' => $devMap['imports'],
                'styles' => $devMap['styles'],
                'isDevServer' => true,
            ],
            $result,
        );
    }

    public function testImportMapFallsBackToStoredMapWhenDevServerAbsent(): void
    {
        $storedMap = ['imports' => ['contena' => '/bundles/frontend/frontend/contena/contena.js']];

        $this->themeScripts->method('getDevImportMap')->willReturn(null);
        $this->themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = new TemplateConfigAccessor(
            $this->themeConfigAccessor,
            $this->themeScripts,
            'dev',
            [],
        );

        static::assertSame($storedMap, $accessor->importMap());
    }

    public function testImportMapIgnoresDevImportMapOutsideDevEnvironment(): void
    {
        // Production / test environments must never return the dev server flag file
        // even if one exists on disk (stale file after a dev/prod switch).
        $storedMap = ['imports' => ['contena' => '/bundles/frontend/frontend/contena/contena.js']];

        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->expects($this->never())->method('getDevImportMap');
        $themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = $this->createAccessor(themeScripts: $themeScripts);

        $result = $accessor->importMap();

        static::assertSame($storedMap, $result);
        static::assertArrayNotHasKey('isDevServer', $result);
    }

    public function testThemeCssVarsReturnsEmptyArrayWhenNoVars(): void
    {
        $this->themeConfigAccessor->method('getCssVarValues')->willReturn([]);
        static::assertSame([], $this->accessor->themeCssVars(Generator::generateChannelContext(), 'theme-id'));
    }

    public function testThemeCssVarsDelegatesToAccessorWithContextAndThemeId(): void
    {
        $context = Generator::generateChannelContext();

        $themeConfigAccessor = $this->createMock(ThemeConfigValueAccessor::class);
        $themeConfigAccessor->expects($this->once())
            ->method('getCssVarValues')
            ->with($context, 'theme-id-abc')
            ->willReturn(['ct-color-brand-primary' => '#0042a0']);

        $accessor = $this->createAccessor(themeConfigAccessor: $themeConfigAccessor);

        $result = $accessor->themeCssVars($context, 'theme-id-abc');

        static::assertSame(['ct-color-brand-primary' => '#0042a0'], $result);
    }

    private function createAccessor(
        ?ThemeConfigValueAccessor $themeConfigAccessor = null,
        ?ThemeScripts $themeScripts = null,
        string $env = 'prod',
    ): TemplateConfigAccessor {
        return new TemplateConfigAccessor(
            $themeConfigAccessor ?? $this->themeConfigAccessor,
            $themeScripts ?? $this->themeScripts,
            $env,
        );
    }
}
