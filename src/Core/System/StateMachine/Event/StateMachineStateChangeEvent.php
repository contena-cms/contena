<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Contena\Core\System\StateMachine\StateMachineEntity;
use Contena\Core\System\StateMachine\Transition;
use Symfony\Contracts\EventDispatcher\Event;

class StateMachineStateChangeEvent extends Event
{
    final public const string STATE_MACHINE_TRANSITION_SIDE_ENTER = 'state_enter';
    final public const string STATE_MACHINE_TRANSITION_SIDE_LEAVE = 'state_leave';

    protected string $channelId;

    protected string $stateName;

    public function __construct(
        protected Context $context,
        protected string $transitionSide,
        protected Transition $transition,
        protected StateMachineEntity $stateMachine,
        protected StateMachineStateEntity $previousState,
        protected StateMachineStateEntity $nextState,
        private readonly ?MailRecipientStruct $mailRecipientStruct = null
    ) {
        if ($this->transitionSide === static::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            $this->stateName = $this->nextState->getTechnicalName();
        } else {
            $this->stateName = $this->previousState->getTechnicalName();
        }
    }

    public function getName(): string
    {
        return 'state_machine.' . $this->stateMachine->getTechnicalName() . '_changed';
    }

    public function getStateEventName(): string
    {
        return $this->transitionSide . '.' . $this->stateMachine->getTechnicalName() . '.' . $this->stateName;
    }

    public function getChannelId(): ?string
    {
        return $this->channelId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getTransition(): Transition
    {
        return $this->transition;
    }

    public function getNextState(): StateMachineStateEntity
    {
        return $this->nextState;
    }

    public function getPreviousState(): StateMachineStateEntity
    {
        return $this->previousState;
    }

    public function getStateName(): string
    {
        return $this->stateName;
    }

    public function getTransitionSide(): string
    {
        return $this->transitionSide;
    }

    public function getStateMachine(): StateMachineEntity
    {
        return $this->stateMachine;
    }

    public function getMailRecipientStruct(): ?MailRecipientStruct
    {
        return $this->mailRecipientStruct;
    }
}
