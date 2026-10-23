<?php declare(strict_types=1);

namespace Contena\Frontend\Test\Theme;

use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\ThemeRuntimeConfig;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;

/**
 * @internal
 */
class ThemeRuntimeConfigTestService extends ThemeRuntimeConfigService
{
    /**
     * @var array<string, ThemeRuntimeConfig>
     */
    private array $configs = [];

    public function __construct(FrontendPluginConfigurationCollection $configurationCollection)
    {
        foreach ($configurationCollection as $plugin) {
            if (!$plugin->getIsTheme()) {
                continue;
            }

            $this->configs[$plugin->getTechnicalName()] = ThemeRuntimeConfig::fromArray([
                'themeId' => Uuid::randomHex(),
                'technicalName' => $plugin->getTechnicalName(),
                'viewInheritance' => $plugin->getViewInheritance(),
            ]);
        }
    }

    public function getActiveThemeNames(): array
    {
        return array_keys($this->configs);
    }

    public function getRuntimeConfigByName(string $technicalName): ?ThemeRuntimeConfig
    {
        return $this->configs[$technicalName] ?? null;
    }
}
