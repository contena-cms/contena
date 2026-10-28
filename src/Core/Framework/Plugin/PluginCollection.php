<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PluginEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PluginCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'plugin_collection';
    }

    protected function getExpectedClass(): string
    {
        return PluginEntity::class;
    }
}
