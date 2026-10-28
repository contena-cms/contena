<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\DataTransfer\PluginMapping;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<PluginMapping>
 */
#[Package('discovery')]
class PluginMappingCollection extends Collection
{
    public function add($element): void
    {
        $this->set($element->pluginName, $element);
    }

    public function set($key, $element): void
    {
        parent::set($element->pluginName, $element);
    }

    protected function getExpectedClass(): string
    {
        return PluginMapping::class;
    }
}
