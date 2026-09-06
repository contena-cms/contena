<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Notification\Struct;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class PaymentNotificationTarget extends Struct
{
    public function __construct(
        public readonly string $entityName,
        public readonly string $entityId,
        public readonly Context $context,
        public readonly string $associationName,
    ) {
    }
}
