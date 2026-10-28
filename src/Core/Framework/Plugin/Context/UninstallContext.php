<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Context;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationCollection;
use Contena\Core\Framework\Plugin;

#[Package('framework')]
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
     * If false is returned, the plugin should remove its data (e.g. its tables) in `uninstall()`.
     * Contena then also removes the plugin's entries in the `migration` table, its system config,
     * custom entities and custom fields, and its theme.
     */
    public function keepUserData(): bool
    {
        return $this->keepUserData;
    }
}
