<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class QueryRequest extends Struct
{
    public function __construct(
        #[SerializedName('order_no')]
        #[Assert\Length(max: 64)]
        public readonly ?string $orderNo = null,
        #[SerializedName('external_order_no')]
        #[Assert\Length(max: 64)]
        public readonly ?string $externalOrderNo = null,
    ) {
    }
}
