<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Payment\Payload\Struct;

use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Contena\Core\Framework\App\Payload\Source;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\CloneTrait;
use Contena\Core\Framework\Struct\JsonSerializableTrait;
use Contena\Core\Framework\Struct\Struct;

#[Package('checkout')]
class PaymentPayload implements PaymentPayloadInterface
{
    use CloneTrait;
    use JsonSerializableTrait;
    use RemoveAppTrait;

    protected Source $source;

    protected OrderTransactionEntity $orderTransaction;

    /**
     * @param mixed[] $requestData
     */
    public function __construct(
        OrderTransactionEntity $orderTransaction,
        protected OrderEntity $order,
        protected array $requestData = [],
        protected ?string $returnUrl = null,
        protected ?Struct $validateStruct = null,
        protected ?RecurringDataStruct $recurring = null,
    ) {
        $this->orderTransaction = $this->removeApp($orderTransaction);
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    /**
     * @return mixed[]
     */
    public function getRequestData(): array
    {
        return $this->requestData;
    }

    public function getReturnUrl(): ?string
    {
        return $this->returnUrl;
    }

    public function getValidateStruct(): ?Struct
    {
        return $this->validateStruct;
    }

    public function getRecurring(): ?RecurringDataStruct
    {
        return $this->recurring;
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
