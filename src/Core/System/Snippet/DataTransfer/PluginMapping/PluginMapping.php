<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\DataTransfer\PluginMapping;

/**
 * @internal
 */
readonly class PluginMapping
{
    public function __construct(
        public string $pluginName,
        public string $snippetName,
    ) {
    }
}
