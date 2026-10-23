<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\ConfigLoader;

use Contena\Core\Framework\Context;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;

abstract class AbstractConfigLoader
{
    abstract public function getDecorated(): AbstractConfigLoader;

    abstract public function load(string $themeId, Context $context): FrontendPluginConfiguration;
}
