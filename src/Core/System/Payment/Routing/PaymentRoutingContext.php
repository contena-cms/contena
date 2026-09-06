<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;

/**
 * @codeCoverageIgnore
 */
final readonly class PaymentRoutingContext
{
    public function __construct(
        public PaymentAppEntity $app,
        public PaymentRoutingRequest $request,
        public Context $context,
    ) {
    }
}
