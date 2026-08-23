<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Theme\AbstractResolvedConfigLoader;
use Contena\Frontend\Theme\ThemeConfigValueAccessor;
use Contena\Frontend\Theme\ThemeRuntimeConfig;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;

/**
 * @internal
 */
#[CoversClass(ThemeConfigValueAccessor::class)]
class ThemeConfigValueAccessorTest extends TestCase
{
    public function testGetWithoutThemeId(): void
    {
        $configLoader = static::createStub(AbstractResolvedConfigLoader::class);
        $cacheTagCollector = static::createStub(CacheTagCollector::class);

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = static::createStub(ChannelContext::class);
        $context->method('getChannelId')->willReturn('channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        $result = $accessor->get('breakpoint.xs', $context, null);

        static::assertNull($result);
    }

    public function testGetWithThemeId(): void
    {
        $configLoader = static::createStub(AbstractResolvedConfigLoader::class);
        $configLoader->method('load')->willReturn([
            'ct-breakpoint-xs' => 0,
            'ct-breakpoint-sm' => 576,
            'ct-breakpoint-md' => 768,
            'ct-breakpoint-lg' => 992,
            'ct-breakpoint-xl' => 1200,
            'ct-breakpoint-xxl' => 1400,
        ]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())->method('addTag');

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = static::createStub(ChannelContext::class);
        $context->method('getChannelId')->willReturn('channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        $result = $accessor->get('breakpoint.xs', $context, 'theme-id');

        static::assertSame(0, $result);
    }

    public function testGetWithThemeIdAndCustomBreakpoints(): void
    {
        $configLoader = static::createStub(AbstractResolvedConfigLoader::class);
        $configLoader->method('load')->willReturn([
            'ct-breakpoint-xs' => 100,
            'ct-breakpoint-sm' => 600,
            'ct-breakpoint-md' => 800,
            'ct-breakpoint-lg' => 1000,
            'ct-breakpoint-xl' => 1300,
            'ct-breakpoint-xxl' => 1500,
        ]);

        $cacheTagCollector = static::createStub(CacheTagCollector::class);

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = static::createStub(ChannelContext::class);
        $context->method('getChannelId')->willReturn('channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        // Test all breakpoint sizes
        static::assertSame(100, $accessor->get('breakpoint.xs', $context, 'theme-id'));
        static::assertSame(600, $accessor->get('breakpoint.sm', $context, 'theme-id'));
        static::assertSame(800, $accessor->get('breakpoint.md', $context, 'theme-id'));
        static::assertSame(1000, $accessor->get('breakpoint.lg', $context, 'theme-id'));
        static::assertSame(1300, $accessor->get('breakpoint.xl', $context, 'theme-id'));
        static::assertSame(1500, $accessor->get('breakpoint.xxl', $context, 'theme-id'));
    }

    public function testGetWithThemeIdAndMissingBreakpoints(): void
    {
        $configLoader = static::createStub(AbstractResolvedConfigLoader::class);
        $configLoader->method('load')->willReturn([
            // No breakpoint configuration provided
        ]);

        $cacheTagCollector = static::createStub(CacheTagCollector::class);

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = static::createStub(ChannelContext::class);
        $context->method('getChannelId')->willReturn('channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        // When breakpoints are missing, should fall back to defaults
        static::assertSame(0, $accessor->get('breakpoint.xs', $context, 'theme-id'));
        static::assertSame(576, $accessor->get('breakpoint.sm', $context, 'theme-id'));
        static::assertSame(768, $accessor->get('breakpoint.md', $context, 'theme-id'));
        static::assertSame(992, $accessor->get('breakpoint.lg', $context, 'theme-id'));
        static::assertSame(1200, $accessor->get('breakpoint.xl', $context, 'theme-id'));
        static::assertSame(1400, $accessor->get('breakpoint.xxl', $context, 'theme-id'));
    }

    public function testGetCachesResults(): void
    {
        $configLoader = $this->createMock(AbstractResolvedConfigLoader::class);
        $configLoader->expects($this->once())->method('load')->willReturn([
            'ct-breakpoint-xs' => 0,
        ]);

        $cacheTagCollector = static::createStub(CacheTagCollector::class);

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = static::createStub(ChannelContext::class);
        $context->method('getChannelId')->willReturn('channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        // First call should load config
        $result1 = $accessor->get('breakpoint.xs', $context, 'theme-id');

        // Second call should use cached config (load should only be called once)
        $result2 = $accessor->get('breakpoint.xs', $context, 'theme-id');

        static::assertSame($result1, $result2);
    }

    public function testGetReturnsNullForNonExistentKey(): void
    {
        $configLoader = static::createStub(AbstractResolvedConfigLoader::class);
        $configLoader->method('load')->willReturn([]);

        $cacheTagCollector = static::createStub(CacheTagCollector::class);

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = static::createStub(ChannelContext::class);
        $context->method('getChannelId')->willReturn('channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        $result = $accessor->get('non.existent.key', $context, 'theme-id');

        static::assertNull($result);
    }

    public function testGetWithAssetsConfig(): void
    {
        $configLoader = static::createStub(AbstractResolvedConfigLoader::class);
        $configLoader->method('load')->willReturn([]);

        $cacheTagCollector = static::createStub(CacheTagCollector::class);

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = static::createStub(ChannelContext::class);
        $context->method('getChannelId')->willReturn('channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        // Test that assets configuration is properly set
        $cssList = $accessor->get('assets.css', $context, 'theme-id');
        $jsList = $accessor->get('assets.js', $context, 'theme-id');

        static::assertIsArray($cssList);
        static::assertContains('/css/all.css', $cssList);
        static::assertIsArray($jsList);
        static::assertContains('/js/all.js', $jsList);
    }

    public function testGetCssVarValuesReturnsEmptyWhenThemeIdMissing(): void
    {
        $accessor = new ThemeConfigValueAccessor(
            static::createStub(AbstractResolvedConfigLoader::class),
            static::createStub(CacheTagCollector::class),
            static::createStub(ThemeRuntimeConfigService::class),
        );

        static::assertSame([], $accessor->getCssVarValues($this->createContext(), null));
    }

    public function testGetCssVarValuesReturnsEmptyWhenRuntimeConfigServiceNotWired(): void
    {
        // Pre-v6.8 the service is nullable and resolves to [] when missing.
        $accessor = new ThemeConfigValueAccessor(
            static::createStub(AbstractResolvedConfigLoader::class),
            static::createStub(CacheTagCollector::class),
        );

        static::assertSame([], $accessor->getCssVarValues($this->createContext(), 'theme-id'));
    }

    public function testGetCssVarValuesReturnsEmptyWhenRuntimeConfigMissing(): void
    {
        $runtimeConfigService = static::createStub(ThemeRuntimeConfigService::class);
        $runtimeConfigService->method('getRuntimeConfig')->willReturn(null);

        $accessor = new ThemeConfigValueAccessor(
            static::createStub(AbstractResolvedConfigLoader::class),
            static::createStub(CacheTagCollector::class),
            $runtimeConfigService,
        );

        static::assertSame([], $accessor->getCssVarValues($this->createContext(), 'theme-id'));
    }

    public function testGetCssVarValuesEmitsSimpleStringValues(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'ct-color-brand-primary' => ['type' => 'color'],
                'ct-font-family-base' => ['type' => 'fontFamily'],
            ],
            resolvedValues: [
                'ct-color-brand-primary' => '#0042a0',
                'ct-font-family-base' => 'Inter, sans-serif',
            ],
        );

        static::assertSame(
            [
                'ct-color-brand-primary' => '#0042a0',
                'ct-font-family-base' => 'Inter, sans-serif',
            ],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesSanitizesUnsafeCharactersInPropertyNameAndValue(): void
    {
        $unsafeKey = "ct-danger;}\n{name";

        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                $unsafeKey => ['type' => 'text'],
            ],
            resolvedValues: [
                $unsafeKey => 'red; } body { color: blue',
            ],
        );

        static::assertSame(
            ['ct-dangername' => 'red\\3B  \\7D  body \\7B  color: blue'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesSanitizesHtmlSensitiveCharacters(): void
    {
        $unsafeKey = 'ct-bad<>&"\'key';

        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                $unsafeKey => ['type' => 'text'],
            ],
            resolvedValues: [
                $unsafeKey => '</style>&',
            ],
        );

        static::assertSame(
            ['ct-badkey' => '\\3C /style\\3E \\26 '],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesPreventsStyleTagBreakoutScriptInjection(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'ct-danger' => ['type' => 'text'],
            ],
            resolvedValues: [
                'ct-danger' => '</style><script>alert(1)</script>',
            ],
        );

        static::assertSame(
            ['ct-danger' => '\\3C /style\\3E \\3C script\\3E alert(1)\\3C /script\\3E '],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesSkipsFieldsWithScssFalse(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'ct-color-brand-primary' => ['type' => 'color'],
                'ct-internal-value' => ['type' => 'text', 'scss' => false],
            ],
            resolvedValues: [
                'ct-color-brand-primary' => '#0042a0',
                'ct-internal-value' => 'should not appear',
            ],
        );

        $result = $accessor->getCssVarValues($this->createContext(), 'theme-id');

        static::assertArrayHasKey('ct-color-brand-primary', $result);
        static::assertArrayNotHasKey('ct-internal-value', $result);
    }

    public function testGetCssVarValuesSkipsNullArrayAndBoolValues(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'null-value' => ['type' => 'text'],
                'array-value' => ['type' => 'text'],
                'bool-value' => ['type' => 'text'],
                'kept' => ['type' => 'color'],
            ],
            resolvedValues: [
                'null-value' => null,
                'array-value' => ['nested'],
                'bool-value' => true,
                'kept' => '#fff',
            ],
        );

        static::assertSame(
            ['kept' => '#fff'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesCastsSwitchAndCheckboxToInt(): void
    {
        // Values come from the DB as stringified numbers; bool values are filtered
        // out upstream by the `is_bool` guard, so the cast here uses numeric input.
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'ct-flag-on' => ['type' => 'switch'],
                'ct-flag-off' => ['type' => 'checkbox'],
            ],
            resolvedValues: [
                'ct-flag-on' => '1',
                'ct-flag-off' => '0',
            ],
        );

        static::assertSame(
            ['ct-flag-on' => 1, 'ct-flag-off' => 0],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesWrapsMediaUrlInUrlFunction(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'ct-logo-desktop' => ['type' => 'media'],
            ],
            resolvedValues: [
                'ct-logo-desktop' => 'https://cdn.example.com/logo.png',
            ],
        );

        static::assertSame(
            ['ct-logo-desktop' => 'url(\'https://cdn.example.com/logo.png\')'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesWrapsUrlFieldInUrlFunction(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'ct-external-background' => ['type' => 'url'],
            ],
            resolvedValues: [
                'ct-external-background' => 'https://example.test/background.png',
            ],
        );

        static::assertSame(
            ['ct-external-background' => 'url(\'https://example.test/background.png\')'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesEscapesQuotedMediaUrlInUrlFunction(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'ct-logo-desktop' => ['type' => 'media'],
            ],
            resolvedValues: [
                'ct-logo-desktop' => 'https://cdn.example.com/foo\')bar\\baz.png',
            ],
        );

        static::assertSame(
            ['ct-logo-desktop' => 'url(\'https://cdn.example.com/foo\\\')bar\\\\baz.png\')'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesSanitizesUnsafeCharactersInMediaUrlValue(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'ct-logo-desktop' => ['type' => 'media'],
            ],
            resolvedValues: [
                'ct-logo-desktop' => 'https://cdn.example.com/logo.png?a=1;b=2}{',
            ],
        );

        static::assertSame(
            ['ct-logo-desktop' => 'url(\'https://cdn.example.com/logo.png?a=1\\3B b=2\\7D \\7B \')'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesSkipsUnresolvedMediaUuid(): void
    {
        // A bare UUID indicates the media ID could not be resolved to a public URL.
        // Emitting it would produce `url(<uuid>)` which is broken.
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'ct-logo-desktop' => ['type' => 'media'],
            ],
            resolvedValues: [
                'ct-logo-desktop' => Uuid::randomHex(),
            ],
        );

        static::assertSame([], $accessor->getCssVarValues($this->createContext(), 'theme-id'));
    }

    /**
     * @param array<string, array{type: string, scss?: bool}> $fields
     * @param array<string, mixed> $resolvedValues
     * @param array<string, string|int> $expected
     */
    #[DataProvider('cssVarExpressionCases')]
    public function testGetCssVarValuesHandlesScssAndCssExpressions(
        array $fields,
        array $resolvedValues,
        array $expected
    ): void {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: $fields,
            resolvedValues: $resolvedValues,
        );

        static::assertSame(
            $expected,
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesSkipsEntryWhenSanitizedKeyIsEmpty(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                "\n;\r{}" => ['type' => 'text'],
            ],
            resolvedValues: [
                "\n;\r{}" => 'value',
            ],
        );

        static::assertSame([], $accessor->getCssVarValues($this->createContext(), 'theme-id'));
    }

    /**
     * @return iterable<string, array{
     *     0: array<string, array{type: string, scss?: bool}>,
     *     1: array<string, mixed>,
     *     2: array<string, string|int>
     * }>
     */
    public static function cssVarExpressionCases(): iterable
    {
        yield 'skips_scss_color_functions_and_keeps_literal_color' => [
            [
                'darken-literal' => ['type' => 'color'],
                'darken-var' => ['type' => 'color'],
                'lighten-call' => ['type' => 'color'],
                'hsl-with-scss-hue' => ['type' => 'color'],
                'kept' => ['type' => 'color'],
            ],
            [
                'darken-literal' => 'darken(#0042a0, 5%)',
                'darken-var' => 'darken($ct-color-brand-primary, 5%)',
                'lighten-call' => 'lighten(#fff, 10%)',
                'hsl-with-scss-hue' => 'hsl(hue($ct-border-color), 20%, 30%)',
                'kept' => '#abcdef',
            ],
            ['kept' => '#abcdef'],
        ];

        yield 'keeps_css_native_functions' => [
            [
                'ct-rgba' => ['type' => 'color'],
                'ct-calc' => ['type' => 'text'],
                'ct-gradient' => ['type' => 'color'],
            ],
            [
                'ct-rgba' => 'rgba(0, 0, 0, 0.5)',
                'ct-calc' => 'calc(100% - 16px)',
                'ct-gradient' => 'linear-gradient(to bottom, #fff, #000)',
            ],
            [
                'ct-rgba' => 'rgba(0, 0, 0, 0.5)',
                'ct-calc' => 'calc(100% - 16px)',
                'ct-gradient' => 'linear-gradient(to bottom, #fff, #000)',
            ],
        ];

        yield 'converts_bare_scss_variable_to_css_var' => [
            [
                'ct-color-brand-secondary' => ['type' => 'color'],
            ],
            [
                'ct-color-brand-secondary' => '$ct-color-brand-primary',
            ],
            ['ct-color-brand-secondary' => 'var(--ct-color-brand-primary)'],
        ];

        yield 'skips_scss_variable_inside_non_whitelisted_function' => [
            [
                'ct-complex' => ['type' => 'text'],
            ],
            [
                'ct-complex' => 'my-function($ct-color-brand-primary, 2)',
            ],
            [],
        ];

        yield 'skips_scss_directive_expressions' => [
            [
                'ct-invalid-default' => ['type' => 'text'],
                'ct-invalid-interpolation' => ['type' => 'text'],
                'ct-invalid-at-rule' => ['type' => 'text'],
                'ct-invalid-block' => ['type' => 'text'],
            ],
            [
                'ct-invalid-default' => '$ct-color-brand-primary !default',
                'ct-invalid-interpolation' => '#{$ct-color-brand-primary}',
                'ct-invalid-at-rule' => '@if $ct-color-brand-primary { color: red; }',
                'ct-invalid-block' => '$ct-color-brand-primary; color: red',
            ],
            [],
        ];

        yield 'converts_safe_scss_variable_expressions_to_css_vars' => [
            [
                'ct-spacing-expression' => ['type' => 'text'],
                'ct-multi-var-addition' => ['type' => 'text'],
                'ct-percent-expression' => ['type' => 'text'],
                'ct-negative-expression' => ['type' => 'text'],
            ],
            [
                'ct-spacing-expression' => '$spacer * 2',
                'ct-multi-var-addition' => '$spacer + $ct-gap',
                'ct-percent-expression' => '$opacity * 100%',
                'ct-negative-expression' => '$offset * -1',
            ],
            [
                'ct-spacing-expression' => 'var(--spacer) * 2',
                'ct-multi-var-addition' => 'var(--spacer) + var(--ct-gap)',
                'ct-percent-expression' => 'var(--opacity) * 100%',
                'ct-negative-expression' => 'var(--offset) * -1',
            ],
        ];
    }

    /**
     * @param array<string, array{type: string, scss?: bool}> $fields
     * @param array<string, mixed> $resolvedValues
     */
    private function createAccessorWithResolvedConfig(array $fields, array $resolvedValues): ThemeConfigValueAccessor
    {
        $configLoader = static::createStub(AbstractResolvedConfigLoader::class);
        $configLoader->method('load')->willReturn($resolvedValues);

        $runtimeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => 'theme-id',
            'technicalName' => 'Frontend',
            'resolvedConfig' => ['fields' => $fields],
            'viewInheritance' => [],
            'scriptFiles' => null,
            'iconSets' => [],
            'updatedAt' => new \DateTimeImmutable(),
        ]);

        $runtimeConfigService = static::createStub(ThemeRuntimeConfigService::class);
        $runtimeConfigService->method('getRuntimeConfig')->willReturn($runtimeConfig);

        return new ThemeConfigValueAccessor(
            $configLoader,
            static::createStub(CacheTagCollector::class),
            $runtimeConfigService,
        );
    }

    private function createContext(): ChannelContext
    {
        $context = static::createStub(ChannelContext::class);
        $context->method('getChannelId')->willReturn('channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        return $context;
    }
}
