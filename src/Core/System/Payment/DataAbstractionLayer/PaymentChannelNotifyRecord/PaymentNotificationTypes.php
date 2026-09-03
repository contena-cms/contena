<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord;

final class PaymentNotificationTypes
{
    final public const int UNKNOWN = 0;
    final public const int PAYMENT = 1;
    final public const int REFUND = 2;
    final public const int TRANSFER = 3;
    final public const int SUBSCRIPTION = 4;
}
