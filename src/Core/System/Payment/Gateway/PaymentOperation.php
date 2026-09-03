<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

final class PaymentOperation
{
    final public const string PAY = 'pay';
    final public const string QUERY = 'query';
    final public const string REFUND = 'refund';
    final public const string TRANSFER = 'transfer';
    final public const string SUBSCRIBE = 'subscribe';
}
