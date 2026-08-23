<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Event;

use Contena\Core\Framework\Plugin\Context\UninstallContext;
use Contena\Core\Framework\Plugin\PluginEntity;

class PluginPostUninstallEvent extends PluginLifecycleEvent
{
    public function __construct(
        PluginEntity $plugin,
        private readonly UninstallContext $context
    ) {
        parent::__construct($plugin);
    }

    public function getContext(): UninstallContext
    {
        return $this->context;
    }
}
