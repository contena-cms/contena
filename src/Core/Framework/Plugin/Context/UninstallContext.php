<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Context;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin;

class UninstallContext extends InstallContext
{

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
