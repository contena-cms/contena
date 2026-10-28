<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Event;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Context\InstallContext;
use Contena\Core\Framework\Plugin\PluginEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PluginPostInstallEvent extends PluginLifecycleEvent
{
    public function __construct(
        PluginEntity $plugin,
        private readonly InstallContext $context
    ) {
        parent::__construct($plugin);
    }

    public function getContext(): InstallContext
    {
        return $this->context;
    }
}
