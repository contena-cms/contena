<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Struct\Collection;

/**
 * Pseudo immutable collection
 *
 * @extends Collection<PluginCategoryStruct>
 *
 * @codeCoverageIgnore
 */
final class PluginCategoryCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'store_category_collection';
    }

    protected function getExpectedClass(): string
    {
        return PluginCategoryStruct::class;
    }
}
