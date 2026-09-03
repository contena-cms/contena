<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer;

final class PaymentTransferStates
{
    final public const string STATE_MACHINE = 'payment_transfer.state';

    final public const string STATE_PROCESSING = 'processing';

    final public const string STATE_SUCCEEDED = 'succeeded';

    final public const string STATE_FAILED = 'failed';
}
