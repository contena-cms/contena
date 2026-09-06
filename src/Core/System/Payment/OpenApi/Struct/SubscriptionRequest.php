<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Struct;

use Contena\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
final class SubscriptionRequest extends Struct
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        #[SerializedName('external_subscription_no')]
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 64)]
        public readonly string $externalSubscriptionNo,
        #[SerializedName('channel_code')]
        #[Assert\Length(max: 32)]
        public readonly ?string $channel = null,
        #[SerializedName('notify_url')]
        #[Assert\Url(protocols: ['http', 'https'], requireTld: false)]
        public readonly ?string $notifyUrl = null,
        #[SerializedName('return_url')]
        #[Assert\Url(protocols: ['http', 'https'], requireTld: false)]
        public readonly ?string $returnUrl = null,
        #[SerializedName('period_type')]
        #[Assert\Length(max: 16)]
        public readonly ?string $periodType = null,
        #[Assert\Positive]
        public readonly ?int $period = null,
        #[SerializedName('execute_time')]
        public readonly ?\DateTimeInterface $executeTime = null,
        #[SerializedName('single_amount')]
        #[Assert\Positive]
        public readonly ?int $singleAmount = null,
        #[SerializedName('total_amount')]
        #[Assert\Positive]
        public readonly ?int $totalAmount = null,
        #[SerializedName('total_payments')]
        #[Assert\Positive]
        public readonly ?int $totalPayments = null,
        #[SerializedName('channel_extra')]
        #[Assert\Type('array')]
        public readonly array $extra = [],
    ) {
    }
}
