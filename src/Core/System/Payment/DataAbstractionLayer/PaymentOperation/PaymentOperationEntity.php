<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentOperation;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PaymentOperationEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    public protected(set) ?string $tenantId = null;

    public protected(set) string $operationNo;

    public protected(set) string $operation;

    public protected(set) string $status;

    public protected(set) string $channelCode;

    public protected(set) string $channelConfigId;

    public protected(set) ?string $orderId = null;

    public protected(set) ?string $refundId = null;

    public protected(set) ?string $transferId = null;

    public protected(set) ?string $recurringId = null;

    public protected(set) ?string $providerRequestId = null;

    public protected(set) ?string $providerResourceId = null;

    /**
     * @var array<string, mixed>|null
     */
    public protected(set) ?array $requestData = null;

    /**
     * @var array<string, mixed>|null
     */
    public protected(set) ?array $responseData = null;

    public protected(set) ?string $resultCode = null;

    public protected(set) ?string $resultMessage = null;

    public protected(set) ?int $httpStatus = null;

    public protected(set) ?int $durationMs = null;

    public protected(set) ?string $errorClass = null;

    public protected(set) \DateTimeInterface $startedAt;

    public protected(set) ?\DateTimeInterface $completedAt = null;
}
