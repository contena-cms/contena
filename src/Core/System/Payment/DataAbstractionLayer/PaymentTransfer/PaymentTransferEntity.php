<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigEntity;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class PaymentTransferEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    public protected(set) ?string $tenantId = null;

    public protected(set) string $paymentAppId;

    public protected(set) string $transferNo;

    public protected(set) string $externalTransferNo;

    public protected(set) int $amount;

    public protected(set) string $currencyCode;

    public protected(set) string $channelCode;

    public protected(set) string $channelConfigId;

    public protected(set) string $stateId;

    public protected(set) string $payee;

    public protected(set) string $payeeName;

    public protected(set) ?string $remark = null;

    /**
     * @var array<mixed>|null
     */
    public protected(set) ?array $channelExtra = null;

    public protected(set) ?string $channelOrderId = null;

    public protected(set) ?string $channelStatus = null;

    /**
     * @var array<mixed>|null
     */
    public protected(set) ?array $responseData = null;

    public protected(set) ?string $resultCode = null;

    public protected(set) ?string $resultMessage = null;

    public protected(set) ?\DateTimeInterface $successTime = null;

    public protected(set) int $version;

    public protected(set) ?PaymentAppEntity $app = null;

    public protected(set) ?PaymentChannelConfigEntity $channelConfig = null;

    public protected(set) ?StateMachineStateEntity $state = null;
}
