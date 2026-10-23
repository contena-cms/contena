<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Context;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Migration\MigrationCollection;
use Contena\Core\Framework\Plugin;

class InstallContext
{
    private bool $autoMigrate = true;

    public function __construct(
        private readonly Plugin $plugin,
        private readonly Context $context,
        private readonly string $currentContenaVersion,
        private readonly string $currentPluginVersion,
        private readonly MigrationCollection $migrationCollection
    ) {
    }

    public function getPlugin(): Plugin
    {
        return $this->plugin;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCurrentContenaVersion(): string
    {
        return $this->currentContenaVersion;
    }

    public function getCurrentPluginVersion(): string
    {
        return $this->currentPluginVersion;
    }

    public function getMigrationCollection(): MigrationCollection
    {
        return $this->migrationCollection;
    }

    public function isAutoMigrate(): bool
    {
        return $this->autoMigrate;
    }

    public function setAutoMigrate(bool $autoMigrate): void
    {
        $this->autoMigrate = $autoMigrate;
    }
}
