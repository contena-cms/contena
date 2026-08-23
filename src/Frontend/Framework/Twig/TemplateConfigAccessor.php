<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Twig;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Theme\ThemeConfigValueAccessor;
use Contena\Frontend\Theme\ThemeScripts;
use Symfony\Component\Asset\Package as AssetPackage;

class TemplateConfigAccessor
{
    /**
     * @var array<string, AssetPackage>
     */
    private array $packages;

    /**
     * @internal
     *
     * @param iterable<string, AssetPackage> $packages
     */
    public function __construct(
        private readonly ThemeConfigValueAccessor $themeConfigAccessor,
        private readonly ThemeScripts $themeScripts,
        private readonly string $kernelEnvironment = 'prod',
        iterable $packages = [],
    ) {
        $this->packages = \is_array($packages) ? $packages : iterator_to_array($packages);
    }

    /**
     * @return string|bool|array<string, mixed>|float|int|null
     */
    public function theme(string $key, ChannelContext $context, ?string $themeId): string|bool|array|float|int|null
    {
        return $this->themeConfigAccessor->get($key, $context, $themeId);
    }

    /**
     * @return list<string>
     */
    public function scripts(): array
    {
        return array_values($this->themeScripts->getThemeScripts());
    }

    /**
     * @return array{imports: array<string, string>, scopes?: array<string, array<string, string>>, styles?: list<string>, scripts?: list<string>, themeId?: string, isDevServer?: bool}
     */
    public function importMap(): array
    {
        if ($this->kernelEnvironment === 'dev') {
            $devMap = $this->themeScripts->getDevImportMap();
            if ($devMap !== null) {
                return $devMap + ['isDevServer' => true];
            }
        }

        return $this->resolveImportMapUrls($this->themeScripts->getImportMap() ?? ['imports' => []]);
    }

    /**
     * @return array<string, string|int>
     */
    public function themeCssVars(ChannelContext $context, ?string $themeId): array
    {
        return $this->themeConfigAccessor->getCssVarValues($context, $themeId);
    }

    /**
     * @param array{imports: array<string, string>, scopes?: array<string, array<string, string>>, styles?: list<string>, scripts?: list<string>, themeId?: string, isDevServer?: bool} $importMap
     *
     * @return array{imports: array<string, string>, scopes?: array<string, array<string, string>>, styles?: list<string>, scripts?: list<string>, themeId?: string, isDevServer?: bool}
     */
    private function resolveImportMapUrls(array $importMap): array
    {
        $package = $this->packages['asset'] ?? null;
        if ($package === null) {
            return $importMap;
        }

        $resolvedImports = [];
        foreach ($importMap['imports'] as $specifier => $path) {
            $resolvedImports[$specifier] = $package->getUrl($path);
        }
        $importMap['imports'] = $resolvedImports;

        if (isset($importMap['scopes'])) {
            $resolvedScopes = [];
            foreach ($importMap['scopes'] as $scopeKey => $scopedImports) {
                $resolvedScopeKey = $this->stripQueryString($package->getUrl($scopeKey));
                foreach ($scopedImports as $specifier => $path) {
                    $resolvedScopes[$resolvedScopeKey][$specifier] = $package->getUrl($path);
                }
            }
            $importMap['scopes'] = $resolvedScopes;
        }

        if (isset($importMap['styles'])) {
            $importMap['styles'] = array_map(
                static fn (string $path): string => $package->getUrl($path),
                $importMap['styles'],
            );
        }

        return $importMap;
    }

    private function stripQueryString(string $url): string
    {
        $queryPos = strpos($url, '?');

        return $queryPos === false ? $url : substr($url, 0, $queryPos);
    }
}
