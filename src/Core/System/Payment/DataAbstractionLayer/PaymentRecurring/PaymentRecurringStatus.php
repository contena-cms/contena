<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring;

final class PaymentRecurringStatus
{
    final public const int STATUS_PENDING = 0;

    final public const int STATUS_SIGNED = 1;

    final public const int STATUS_UNSIGNED = 2;

    final public const int STATUS_FAILED = 3;
}
