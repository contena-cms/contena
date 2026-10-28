<?php declare(strict_types=1);

namespace Contena\Storefront\Theme\ConfigLoader;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

#[Package('discovery')]
abstract class AbstractConfigLoader
{
    abstract public function getDecorated(): AbstractConfigLoader;

    abstract public function load(string $themeId, Context $context): StorefrontPluginConfiguration;
}
