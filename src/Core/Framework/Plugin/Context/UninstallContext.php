<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Context;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Migration\MigrationCollection;
use Contena\Core\Framework\Plugin;

class UninstallContext extends InstallContext
{
    public function __construct(
        Plugin $plugin,
        Context $context,
        string $currentContenaVersion,
        string $currentPluginVersion,
        MigrationCollection $migrationCollection,
        private readonly bool $keepUserData
    ) {
        parent::__construct($plugin, $context, $currentContenaVersion, $currentPluginVersion, $migrationCollection);
    }

    /**
     * If true is returned, migrations of the plugin will also be removed
     */
    public function keepUserData(): bool
    {
        return $this->keepUserData;
    }
}
