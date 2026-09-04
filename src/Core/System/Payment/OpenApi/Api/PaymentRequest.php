<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Api;

use Contena\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class PaymentRequest extends Struct
{
    /**
     * @internal
     */
    #[Ignore]
    public ?string $clientIp = null;

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        #[SerializedName('external_order_no')]
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public readonly string $externalOrderNo,
        #[Assert\Positive]
        public readonly int $amount,
        #[SerializedName('method_code')]
        #[Assert\NotBlank]
        #[Assert\Length(max: 32)]
        public readonly string $method,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $subject,
        #[SerializedName('currency_code')]
        #[Assert\Length(min: 3, max: 3)]
        public readonly string $currencyCode = 'CNY',
        #[Assert\Length(max: 32)]
        public readonly ?string $channel = null,
        #[SerializedName('device_type')]
        #[Assert\Length(max: 32)]
        public readonly ?string $deviceType = null,
        #[SerializedName('notify_url')]
        #[Assert\Url(protocols: ['http', 'https'], requireTld: false)]
        public readonly ?string $notifyUrl = null,
        #[SerializedName('return_url')]
        #[Assert\Url(protocols: ['http', 'https'], requireTld: false)]
        public readonly ?string $returnUrl = null,
        #[SerializedName('channel_extra')]
        #[Assert\Type('array')]
        public readonly array $extra = [],
    ) {
    }
}
