<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Struct\Collection;

/**
 * Pseudo immutable collection
 *
 * @extends Collection<PluginRegionStruct>
 *
 * @codeCoverageIgnore
 */
final class PluginRegionCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'store_plugin_region_collection';
    }

    protected function getExpectedClass(): string
    {
        return PluginRegionStruct::class;
    }
}
