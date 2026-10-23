<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Store\Struct\FrwState;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class FirstRunWizardStartedEvent extends Event
{
    public function __construct(
        private readonly FrwState $state,
        private readonly Context $context
    ) {
    }

    public function getState(): FrwState
    {
        return $this->state;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
