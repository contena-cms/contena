<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder;

final class PaymentOrderStates
{
    final public const string STATE_MACHINE = 'payment_order.state';

    final public const string STATE_CREATED = 'created';

    final public const string STATE_PENDING = 'pending';

    final public const string STATE_PROCESSING = 'processing';

    final public const string STATE_UNKNOWN = 'unknown';

    final public const string STATE_SUCCEEDED = 'succeeded';

    final public const string STATE_FAILED = 'failed';

    final public const string STATE_CLOSED = 'closed';
}
