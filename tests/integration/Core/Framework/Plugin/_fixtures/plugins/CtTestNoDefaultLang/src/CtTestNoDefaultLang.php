<?php declare(strict_types=1);

namespace CtTestNoDefaultLang;

use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Plugin\Context\DeactivateContext;
use Contena\Core\Framework\Plugin\Context\UninstallContext;
use Contena\Core\Framework\Plugin\Context\UpdateContext;

class CtTestNoDefaultLang extends Plugin
{
    final public const string PLUGIN_LABEL = 'Dutch Pluginname';

    final public const string PLUGIN_VERSION = '1.0.1';

    final public const string PLUGIN_OLD_VERSION = '1.0.0';

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);
    }
}
