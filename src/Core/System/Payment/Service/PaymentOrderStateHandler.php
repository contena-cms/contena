<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderStates;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\System\StateMachine\Transition;

/**
 * Applies provider statuses to payment order state machines.
 *
 * @internal
 */
final class PaymentOrderStateHandler
{
    private const array ORDER_TRANSITIONS = [
        PaymentStatus::PENDING => [
            PaymentOrderStates::STATE_CREATED => 'submit',
            PaymentOrderStates::STATE_UNKNOWN => 'mark_pending',
        ],
        PaymentStatus::PROCESSING => [
            PaymentOrderStates::STATE_CREATED => 'process',
            PaymentOrderStates::STATE_PENDING => 'process',
            PaymentOrderStates::STATE_UNKNOWN => 'process',
        ],
        PaymentStatus::SUCCEEDED => [
            PaymentOrderStates::STATE_CREATED => 'succeed',
            PaymentOrderStates::STATE_PENDING => 'succeed',
            PaymentOrderStates::STATE_PROCESSING => 'succeed',
            PaymentOrderStates::STATE_UNKNOWN => 'succeed',
        ],
        PaymentStatus::FAILED => [
            PaymentOrderStates::STATE_CREATED => 'fail',
            PaymentOrderStates::STATE_PENDING => 'fail',
            PaymentOrderStates::STATE_PROCESSING => 'fail',
            PaymentOrderStates::STATE_UNKNOWN => 'fail',
        ],
        PaymentStatus::CLOSED => [
            PaymentOrderStates::STATE_CREATED => 'close',
            PaymentOrderStates::STATE_PENDING => 'close',
            PaymentOrderStates::STATE_PROCESSING => 'close',
            PaymentOrderStates::STATE_UNKNOWN => 'close',
        ],
        PaymentStatus::UNKNOWN => [
            PaymentOrderStates::STATE_CREATED => 'mark_unknown',
            PaymentOrderStates::STATE_PENDING => 'mark_unknown',
            PaymentOrderStates::STATE_PROCESSING => 'mark_unknown',
        ],
    ];

    public function __construct(private readonly StateMachineRegistry $stateMachineRegistry)
    {
    }

    public function applyOrderStatus(PaymentOrderEntity $order, string $status, Context $context): void
    {
        $currentState = $order->state?->getTechnicalName();
        $transition = $currentState === null ? null : (self::ORDER_TRANSITIONS[$status][$currentState] ?? null);
        if ($transition === null) {
            return;
        }

        $this->stateMachineRegistry->transition(new Transition(PaymentOrderDefinition::ENTITY_NAME, $order->getId(), $transition, 'stateId'), $context);
    }

    public function applyTransactionStatus(string $transactionId, string $status, Context $context): void
    {
        $transition = match ($status) {
            PaymentStatus::SUCCEEDED => 'succeed',
            PaymentStatus::FAILED, PaymentStatus::CLOSED => 'fail',
            default => null,
        };
        if ($transition === null) {
            return;
        }

        $this->stateMachineRegistry->transition(new Transition(PaymentOrderTransactionDefinition::ENTITY_NAME, $transactionId, $transition, 'stateId'), $context);
    }
}
