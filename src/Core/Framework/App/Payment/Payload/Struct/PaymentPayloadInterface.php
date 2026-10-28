<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Payment\Payload\Struct;

use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Contena\Core\Framework\App\Payload\SourcedPayloadInterface;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
interface PaymentPayloadInterface extends SourcedPayloadInterface
{
    public function getOrderTransaction(): OrderTransactionEntity;
}
