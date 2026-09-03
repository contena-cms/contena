<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

final class PaymentStatus
{
    final public const string PENDING = 'pending';
    final public const string PROCESSING = 'processing';
    final public const string SUCCEEDED = 'succeeded';
    final public const string FAILED = 'failed';
    final public const string CLOSED = 'closed';
    final public const string UNKNOWN = 'unknown';
}
