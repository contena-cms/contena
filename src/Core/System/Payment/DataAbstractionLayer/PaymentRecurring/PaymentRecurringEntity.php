<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigEntity;

class PaymentRecurringEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    public protected(set) ?string $tenantId = null;

    public protected(set) string $paymentAppId;

    public protected(set) string $recurringNo;

    public protected(set) string $externalRecurringNo;

    public protected(set) string $channelCode;

    public protected(set) string $channelConfigId;

    public protected(set) ?string $channelRecurringNo = null;

    /**
     * @var array<mixed>|null
     */
    public protected(set) ?array $channelParams = null;

    /**
     * @var array<mixed>|null
     */
    public protected(set) ?array $channelExtra = null;

    public protected(set) ?string $notifyUrl = null;

    public protected(set) ?string $returnUrl = null;

    /**
     * @var array<mixed>|null
     */
    public protected(set) ?array $responseData = null;

    public protected(set) ?string $resultCode = null;

    public protected(set) ?string $resultMessage = null;

    public protected(set) int $status;

    public protected(set) ?\DateTimeInterface $signTime = null;

    public protected(set) ?\DateTimeInterface $expireTime = null;

    public protected(set) ?string $periodType = null;

    public protected(set) ?int $period = null;

    public protected(set) ?\DateTimeInterface $executeTime = null;

    public protected(set) ?int $singleAmount = null;

    public protected(set) ?int $totalAmount = null;

    public protected(set) ?int $totalPayments = null;

    public protected(set) int $version;

    public protected(set) ?PaymentAppEntity $app = null;

    public protected(set) ?PaymentChannelConfigEntity $channelConfig = null;
}
