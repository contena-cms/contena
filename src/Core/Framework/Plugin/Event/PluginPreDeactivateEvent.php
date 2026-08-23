<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Event;

use Contena\Core\Framework\Plugin\Context\DeactivateContext;
use Contena\Core\Framework\Plugin\PluginEntity;

class PluginPreDeactivateEvent extends PluginLifecycleEvent
{
    public function __construct(
        PluginEntity $plugin,
        private readonly DeactivateContext $context
    ) {
        parent::__construct($plugin);
    }

    public function getContext(): DeactivateContext
    {
        return $this->context;
    }
}
