<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class PaymentOrderTransactionEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    public protected(set) ?string $tenantId = null;

    public protected(set) string $orderId;

    public protected(set) string $transactionNo;

    public protected(set) string $type;

    public protected(set) string $channelCode;

    public protected(set) ?string $methodCode = null;

    public protected(set) int $amount;

    public protected(set) ?string $channelRequestNo = null;

    public protected(set) ?string $channelTradeNo = null;

    public protected(set) string $stateId;

    /**
     * @var array<mixed>|null
     */
    public protected(set) ?array $responseData = null;

    public protected(set) ?string $resultCode = null;

    public protected(set) ?string $resultMessage = null;

    public protected(set) ?string $operator = null;

    public protected(set) ?PaymentOrderEntity $order = null;

    public protected(set) ?StateMachineStateEntity $state = null;
}
