<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Event;

use Contena\Core\Framework\Plugin\Context\ActivateContext;
use Contena\Core\Framework\Plugin\PluginEntity;

class PluginPreActivateEvent extends PluginLifecycleEvent
{
    public function __construct(
        PluginEntity $plugin,
        private readonly ActivateContext $context
    ) {
        parent::__construct($plugin);
    }

    public function getContext(): ActivateContext
    {
        return $this->context;
    }
}
