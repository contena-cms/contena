<?php declare(strict_types=1);

namespace Contena\Storefront\Theme\StorefrontPluginConfiguration;

use Contena\Core\Framework\Bundle;
use Contena\Core\Framework\Log\Package;

#[Package('discovery')]
abstract class AbstractStorefrontPluginConfigurationFactory
{
    abstract public function getDecorated(): AbstractStorefrontPluginConfigurationFactory;

    abstract public function createFromBundle(Bundle $bundle): StorefrontPluginConfiguration;

    abstract public function createFromApp(string $appName, string $appPath): StorefrontPluginConfiguration;

    /**
     * @param array<string, mixed> $data
     */
    abstract public function createFromThemeJson(string $name, array $data, string $path): StorefrontPluginConfiguration;
}
