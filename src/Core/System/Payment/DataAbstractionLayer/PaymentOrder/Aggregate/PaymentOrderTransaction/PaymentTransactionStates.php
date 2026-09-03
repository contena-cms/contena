<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction;

final class PaymentTransactionStates
{
    final public const string STATE_MACHINE = 'payment_order_transaction.state';

    final public const string STATE_PROCESSING = 'processing';

    final public const string STATE_SUCCEEDED = 'succeeded';

    final public const string STATE_FAILED = 'failed';
}
