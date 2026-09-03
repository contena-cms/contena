<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord;

final class PaymentNotifyRecordStatus
{
    final public const int STATUS_PENDING = 0;

    final public const int STATUS_PROCESSING = 1;

    final public const int STATUS_SUCCEEDED = 2;

    final public const int STATUS_FAILED = 3;
}
