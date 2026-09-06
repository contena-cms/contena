<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Request;

use Contena\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
final class TransferRequest extends Struct
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        #[SerializedName('external_transfer_no')]
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 64)]
        public readonly string $externalTransferNo,
        #[Assert\Positive]
        public readonly int $amount,
        #[SerializedName('currency_code')]
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-z]{3}$/iD')]
        public readonly string $currencyCode = 'CNY',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 128)]
        public readonly string $payee = '',
        #[SerializedName('payee_name')]
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 64)]
        public readonly string $payeeName = '',
        #[SerializedName('channel_code')]
        #[Assert\Length(max: 32)]
        public readonly ?string $channel = null,
        #[Assert\Length(max: 255)]
        public readonly ?string $remark = null,
        #[SerializedName('notify_url')]
        #[Assert\Url(protocols: ['http', 'https'], requireTld: false)]
        public readonly ?string $notifyUrl = null,
        #[SerializedName('channel_extra')]
        #[Assert\Type('array')]
        public readonly array $extra = [],
    ) {
    }
}
