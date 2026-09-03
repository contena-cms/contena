<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;

final class SubscriptionRequest extends Struct
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $externalSubscriptionNo,
        public readonly ?string $channel = null,
        public readonly ?string $providerSubscriptionNo = null,
        public readonly ?string $notifyUrl = null,
        public readonly ?string $returnUrl = null,
        public readonly array $extra = [],
    ) {
    }
}
