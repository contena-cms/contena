<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\FrontendPluginConfiguration;

use Contena\Core\Framework\Bundle;

abstract class AbstractFrontendPluginConfigurationFactory
{
    abstract public function getDecorated(): self;

    abstract public function createFromBundle(Bundle $bundle): FrontendPluginConfiguration;

    /**
     * @param array<string, mixed> $data
     */
    abstract public function createFromThemeJson(string $name, array $data, string $path): FrontendPluginConfiguration;
}
