<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
final class OrderReference extends Struct
{
    public function __construct(
        public readonly ?string $orderNo = null,
        public readonly ?string $externalOrderNo = null,
    ) {
    }
}
