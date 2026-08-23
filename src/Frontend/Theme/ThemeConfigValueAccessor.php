<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;

class ThemeConfigValueAccessor
{
    /**
     * Matches SCSS-only color and math functions that have no CSS equivalent.
     * Values containing these calls cannot be emitted as CSS custom properties.
     *
     * Deliberately excludes CSS-native functions (rgb, rgba, hsl, hsla, calc,
     * linear-gradient, etc.) so those are emitted unchanged.
     */
    private const string SCSS_FUNCTION_PATTERN = '/\b(?:darken|lighten|saturate|desaturate|mix|adjust-hue|tint|shade|fade-in|fade-out|opacify|transparentize|invert|complement|change-color|adjust-color|scale-color|hue|saturation|lightness|red|green|blue|alpha|opacity)\s*\(/';

    /**
     * @var array<string, mixed>
     */
    private array $themeConfig = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractResolvedConfigLoader $themeConfigLoader,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ?ThemeRuntimeConfigService $themeRuntimeConfigService = null,
    ) {
    }

    /**
     * @return string|bool|array<string, mixed>|float|int|null
     */
    public function get(string $key, ChannelContext $context, ?string $themeId): string|bool|array|float|int|null
    {
        $config = $this->getThemeConfig($context, $themeId);

        return $config[$key] ?? null;
    }

    /**
     * Returns all theme config fields that have `"scss": true` (the default) as a flat
     * key → value map, ready for injection as CSS custom properties.
     *
     * @return array<string, string|int>
     */
    public function getCssVarValues(ChannelContext $context, ?string $themeId): array
    {
        if ($themeId === null || $this->themeRuntimeConfigService === null) {
            return [];
        }

        $runtimeConfig = $this->themeRuntimeConfigService->getRuntimeConfig($themeId);
        if ($runtimeConfig === null) {
            return [];
        }

        $resolvedValues = $this->getThemeConfig($context, $themeId);
        $result = [];

        /** @var array{fields?: array<string, array{value?: mixed, type: string, scss?: bool}>} $config */
        $config = $runtimeConfig->resolvedConfig;

        foreach ($config['fields'] ?? [] as $key => $data) {
            if (
                !\is_array($data)
                || !isset($data['type'])
                || (\array_key_exists('scss', $data) && $data['scss'] === false)
            ) {
                continue;
            }

            $safeKey = $this->sanitizeCssCustomPropertyKey($key);
            if ($safeKey === null) {
                continue;
            }

            $value = $resolvedValues[$key] ?? null;

            if ($value === null || \is_array($value) || \is_bool($value)) {
                continue;
            }

            $type = $data['type'];

            if ($type === 'media' || $type === 'url') {
                if ($type === 'media' && Uuid::isValid((string) $value)) {
                    continue;
                }

                $escapedUrl = \addcslashes((string) $value, "\\'\n\r");
                $result[$safeKey] = $this->sanitizeCssCustomPropertyValue(\sprintf('url(\'%s\')', $escapedUrl));

                continue;
            }

            if ($type === 'switch' || $type === 'checkbox') {
                $result[$safeKey] = (int) $value;

                continue;
            }

            $stringValue = (string) $value;

            if (preg_match(self::SCSS_FUNCTION_PATTERN, $stringValue)) {
                continue;
            }

            if (str_contains($stringValue, '$')) {
                if (!$this->isSafeScssVariableExpression($stringValue)) {
                    continue;
                }

                $stringValue = (string) preg_replace('/\$([a-zA-Z][a-zA-Z0-9_-]*)/', 'var(--$1)', $stringValue);
            }

            $result[$safeKey] = $this->sanitizeCssCustomPropertyValue($stringValue);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function getThemeConfig(ChannelContext $context, ?string $themeId): array
    {
        $key = $context->getChannelId() . $context->getDomainId() . $themeId;

        if (isset($this->themeConfig[$key])) {
            return $this->themeConfig[$key];
        }

        if (!$themeId) {
            return $this->themeConfig[$key] = [];
        }

        $this->cacheTagCollector->addTag(ThemeConfigCacheInvalidator::buildCacheTag($themeId));

        $themeConfig = array_merge(
            [
                'assets' => [
                    'css' => ['/css/all.css'],
                    'js' => ['/js/all.js'],
                ],
            ],
            $this->themeConfigLoader->load($themeId, $context)
        );

        $themeConfig = array_merge(
            $themeConfig,
            [
                'breakpoint' => [
                    'xs' => $themeConfig['ct-breakpoint-xs'] ?? 0,
                    'sm' => $themeConfig['ct-breakpoint-sm'] ?? 576,
                    'md' => $themeConfig['ct-breakpoint-md'] ?? 768,
                    'lg' => $themeConfig['ct-breakpoint-lg'] ?? 992,
                    'xl' => $themeConfig['ct-breakpoint-xl'] ?? 1200,
                    'xxl' => $themeConfig['ct-breakpoint-xxl'] ?? 1400,
                ],
            ]
        );

        return $this->themeConfig[$key] = $this->flatten($themeConfig, null);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function flatten(array $values, ?string $prefix): array
    {
        $prefix = $prefix ? $prefix . '.' : '';
        $flat = [];
        foreach ($values as $key => $value) {
            $isNested = \is_array($value) && !isset($value[0]);

            if (!$isNested) {
                $flat[$prefix . $key] = $value;

                continue;
            }

            $nested = $this->flatten($value, $prefix . $key);
            foreach ($nested as $nestedKey => $nestedValue) {
                $flat[$nestedKey] = $nestedValue;
            }
        }

        return $flat;
    }

    private function isSafeScssVariableExpression(string $value): bool
    {
        if (str_contains($value, '(')) {
            return false;
        }

        if (preg_match('/[!@;{}]|#\{/', $value) === 1) {
            return false;
        }

        if (preg_match('/^[\s$#%.,:+\-*\/_a-zA-Z0-9-]+$/', $value) !== 1) {
            return false;
        }

        return preg_match('/\$(?![a-zA-Z][a-zA-Z0-9_-]*)/', $value) !== 1;
    }

    private function sanitizeCssCustomPropertyKey(string $key): ?string
    {
        $sanitizedKey = str_replace(["\n", "\r", ';', '{', '}', '<', '>', '&', '"', '\''], '', $key);

        return $sanitizedKey !== '' ? $sanitizedKey : null;
    }

    private function sanitizeCssCustomPropertyValue(string $value): string
    {
        return str_replace(
            [';', '{', '}', "\n", "\r", '<', '>', '&'],
            ['\\3B ', '\\7B ', '\\7D ', ' ', ' ', '\\3C ', '\\3E ', '\\26 '],
            $value
        );
    }
}
