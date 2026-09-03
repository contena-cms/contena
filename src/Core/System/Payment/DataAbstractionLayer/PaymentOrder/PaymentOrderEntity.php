<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentChannelNotifyRecordCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class PaymentOrderEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    public protected(set) ?string $tenantId = null;

    public protected(set) string $paymentAppId;

    public protected(set) string $orderNo;

    public protected(set) string $externalOrderNo;

    public protected(set) int $amount;

    public protected(set) int $refundedAmount;

    public protected(set) string $currencyCode;

    public protected(set) string $channelCode;

    public protected(set) string $methodCode;

    public protected(set) string $deviceType;

    public protected(set) string $subject;

    public protected(set) ?string $clientIp = null;

    /**
     * @var array<mixed>|null
     */
    public protected(set) ?array $channelExtra = null;

    public protected(set) ?string $notifyUrl = null;

    public protected(set) string $stateId;

    public protected(set) ?string $closeReason = null;

    public protected(set) ?string $channelTradeNo = null;

    public protected(set) string $channelConfigId;

    public protected(set) ?\DateTimeInterface $successTime = null;

    public protected(set) ?\DateTimeInterface $expireTime = null;

    public protected(set) int $version;

    public protected(set) ?string $primaryTransactionId = null;

    public protected(set) ?string $recurringId = null;

    public protected(set) ?PaymentAppEntity $app = null;

    public protected(set) ?StateMachineStateEntity $state = null;

    public protected(set) ?PaymentChannelConfigEntity $channelConfig = null;

    public protected(set) ?PaymentRecurringEntity $recurring = null;

    public protected(set) ?PaymentOrderTransactionEntity $primaryTransaction = null;

    public protected(set) ?PaymentRefundCollection $refunds = null;

    public protected(set) ?PaymentOrderTransactionCollection $transactions = null;

    public protected(set) ?PaymentNotifyRecordCollection $notifyRecords = null;

    public protected(set) ?PaymentChannelNotifyRecordCollection $channelNotifyRecords = null;
}
