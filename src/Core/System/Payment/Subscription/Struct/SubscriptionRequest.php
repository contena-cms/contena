<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Subscription\Struct;

use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
final class SubscriptionRequest extends Struct
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $externalSubscriptionNo,
        public readonly ?string $channel = null,
        public readonly ?string $notifyUrl = null,
        public readonly ?string $returnUrl = null,
        public readonly ?string $periodType = null,
        public readonly ?int $period = null,
        public readonly ?\DateTimeInterface $executeTime = null,
        public readonly ?int $singleAmount = null,
        public readonly ?int $totalAmount = null,
        public readonly ?int $totalPayments = null,
        public readonly array $extra = [],
    ) {
    }
}
