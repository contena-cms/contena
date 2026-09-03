<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentChannelNotifyRecordCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;

class PaymentRefundEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    public protected(set) ?string $tenantId = null;

    public protected(set) string $refundNo;

    public protected(set) string $orderId;

    public protected(set) string $externalRefundNo;

    public protected(set) int $refundAmount;

    public protected(set) string $channelCode;

    public protected(set) int $status;

    public protected(set) ?string $channelRefundNo = null;

    public protected(set) ?\DateTimeInterface $successTime = null;

    public protected(set) ?string $reason = null;

    /**
     * @var array<mixed>|null
     */
    public protected(set) ?array $responseData = null;

    public protected(set) ?string $resultCode = null;

    public protected(set) ?string $resultMessage = null;

    public protected(set) int $version;

    public protected(set) ?PaymentOrderEntity $order = null;

    public protected(set) ?PaymentNotifyRecordCollection $notifyRecords = null;

    public protected(set) ?PaymentChannelNotifyRecordCollection $channelNotifyRecords = null;
}
