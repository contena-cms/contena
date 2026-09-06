<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Struct;

use Contena\Core\Framework\Struct\Struct;
use Contena\Tests\Integration\Core\System\Payment\OpenApi\OpenApiTest;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @codeCoverageIgnore
 *
 * @see OpenApiTest
 */
final class RefundRequest extends Struct
{
    public function __construct(
        #[SerializedName('external_refund_no')]
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 64)]
        public readonly string $externalRefundNo,
        #[SerializedName('refund_amount')]
        #[Assert\Positive]
        public readonly int $amount,
        #[SerializedName('order_no')]
        #[Assert\Length(max: 64)]
        public readonly ?string $orderNo = null,
        #[SerializedName('external_order_no')]
        #[Assert\Length(max: 64)]
        public readonly ?string $externalOrderNo = null,
        #[Assert\Length(max: 255)]
        public readonly ?string $reason = null,
    ) {
    }

    #[Assert\Callback]
    public function validateReference(ExecutionContextInterface $context): void
    {
        if (trim($this->orderNo ?? '') === '' && trim($this->externalOrderNo ?? '') === '') {
            $context->buildViolation('An order number or external order number is required.')->addViolation();
        }
    }
}
