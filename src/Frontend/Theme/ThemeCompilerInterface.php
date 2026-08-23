<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Core\Framework\Context;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;

interface ThemeCompilerInterface
{
    public function compileTheme(
        string $channelId,
        string $themeId,
        FrontendPluginConfiguration $themeConfig,
        FrontendPluginConfigurationCollection $configurationCollection,
        bool $withAssets,
        Context $context
    ): void;
}
