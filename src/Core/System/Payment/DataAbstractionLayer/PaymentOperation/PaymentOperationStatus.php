<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentOperation;

final class PaymentOperationStatus
{
    final public const string CREATED = 'created';

    final public const string SENT = 'sent';

    final public const string SUCCEEDED = 'succeeded';

    final public const string FAILED = 'failed';

    final public const string UNKNOWN = 'unknown';
}
