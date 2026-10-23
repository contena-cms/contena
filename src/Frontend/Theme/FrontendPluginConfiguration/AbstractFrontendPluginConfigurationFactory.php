<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\FrontendPluginConfiguration;

use Contena\Core\Framework\Bundle;
use Contena\Core\Framework\Log\Package;

abstract class AbstractFrontendPluginConfigurationFactory
{
    abstract public function getDecorated(): AbstractFrontendPluginConfigurationFactory;

    abstract public function createFromBundle(Bundle $bundle): FrontendPluginConfiguration;

    abstract public function createFromApp(string $appName, string $appPath): FrontendPluginConfiguration;

    /**
     * @param array<string, mixed> $data
     */
    abstract public function createFromThemeJson(string $name, array $data, string $path): FrontendPluginConfiguration;
}
