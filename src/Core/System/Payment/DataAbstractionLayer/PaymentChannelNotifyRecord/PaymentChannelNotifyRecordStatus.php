<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord;

final class PaymentChannelNotifyRecordStatus
{
    final public const int STATUS_PENDING = 0;

    final public const int STATUS_PROCESSED = 1;

    final public const int STATUS_IGNORED = 2;

    final public const int STATUS_FAILED = 3;
}
