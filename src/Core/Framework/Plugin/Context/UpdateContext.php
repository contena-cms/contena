<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Context;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Migration\MigrationCollection;
use Contena\Core\Framework\Plugin;

class UpdateContext extends InstallContext
{
    public function __construct(
        Plugin $plugin,
        Context $context,
        string $currentContenaVersion,
        string $currentPluginVersion,
        MigrationCollection $migrationCollection,
        private readonly string $updatePluginVersion
    ) {
        parent::__construct($plugin, $context, $currentContenaVersion, $currentPluginVersion, $migrationCollection);
    }

    public function getUpdatePluginVersion(): string
    {
        return $this->updatePluginVersion;
    }
}
