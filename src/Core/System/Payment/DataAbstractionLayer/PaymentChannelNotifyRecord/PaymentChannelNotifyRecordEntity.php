<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;

class PaymentChannelNotifyRecordEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    public protected(set) ?string $tenantId = null;

    public protected(set) string $channelCode;

    public protected(set) string $channelConfigId;

    public protected(set) string $notificationKey;

    public protected(set) ?string $orderId = null;

    public protected(set) ?string $refundId = null;

    public protected(set) ?string $transferId = null;

    public protected(set) ?string $recurringId = null;

    public protected(set) int $notifyType;

    public protected(set) ?string $rawBody = null;

    public protected(set) ?string $responseBody = null;

    public protected(set) ?string $responseContentType = null;

    public protected(set) ?int $responseStatus = null;

    public protected(set) int $status;

    public protected(set) ?string $errorMessage = null;

    public protected(set) ?PaymentOrderEntity $order = null;

    public protected(set) ?PaymentRefundEntity $refund = null;

    public protected(set) ?PaymentTransferEntity $transfer = null;

    public protected(set) ?PaymentRecurringEntity $recurring = null;

    public protected(set) ?PaymentChannelConfigEntity $channelConfig = null;
}
