<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Payment\Payload\Struct;

use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Payment\PaymentException;
use Contena\Core\Framework\App\Payload\Source;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\CloneTrait;
use Contena\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class RefundPayload implements PaymentPayloadInterface
{
    use CloneTrait;
    use JsonSerializableTrait;
    use RemoveAppTrait;

    protected Source $source;

    protected OrderTransactionCaptureRefundEntity $refund;

    public function __construct(
        OrderTransactionCaptureRefundEntity $refund,
        protected OrderEntity $order
    ) {
        if ($refund->getTransactionCapture() && $refund->getTransactionCapture()->getTransaction()) {
            $transaction = $this->removeApp($refund->getTransactionCapture()->getTransaction());
            $refund->getTransactionCapture()->setTransaction($transaction);
        }

        $this->refund = $refund;
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        if ($this->refund->getTransactionCapture() && $this->refund->getTransactionCapture()->getTransaction()) {
            return $this->refund->getTransactionCapture()->getTransaction();
        }

        throw PaymentException::refundInterrupted($this->refund->getId(), 'No transaction found for refund.');
    }

    public function getRefund(): OrderTransactionCaptureRefundEntity
    {
        return $this->refund;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }
}
