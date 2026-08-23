<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Event;

use Contena\Core\Framework\Plugin\Context\UpdateContext;
use Contena\Core\Framework\Plugin\PluginEntity;

class PluginPostUpdateEvent extends PluginLifecycleEvent
{
    public function __construct(
        PluginEntity $plugin,
        private readonly UpdateContext $context
    ) {
        parent::__construct($plugin);
    }

    public function getContext(): UpdateContext
    {
        return $this->context;
    }
}
