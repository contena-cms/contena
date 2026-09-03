<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund;

final class PaymentRefundStatus
{
    final public const int STATUS_CREATED = 0;

    final public const int STATUS_PROCESSING = 1;

    final public const int STATUS_SUCCEEDED = 2;

    final public const int STATUS_FAILED = 3;
}
