<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine\Aggregation\StateMachineTransition;

final class StateMachineTransitionActions
{
    public const string ACTION_CANCEL = 'cancel';
    public const string ACTION_COMPLETE = 'complete';

    public const string ACTION_FAIL = 'fail';
    public const string ACTION_PAID = 'paid';
    public const string ACTION_PAID_PARTIALLY = 'paid_partially';
    public const string ACTION_PROCESS = 'process';
    public const string ACTION_PROCESS_UNCONFIRMED = 'process_unconfirmed';
    public const string ACTION_REFUND = 'refund';
    public const string ACTION_REFUND_PARTIALLY = 'refund_partially';
    public const string ACTION_REMIND = 'remind';
    public const string ACTION_REOPEN = 'reopen';
    public const string ACTION_RETOUR = 'retour';
    public const string ACTION_RETOUR_PARTIALLY = 'retour_partially';
    public const string ACTION_SHIP = 'ship';
    public const string ACTION_SHIP_PARTIALLY = 'ship_partially';
    public const string ACTION_AUTHORIZE = 'authorize';
    public const string ACTION_CHARGEBACK = 'chargeback';
}
