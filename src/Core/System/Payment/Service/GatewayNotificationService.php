<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\PaymentChannelEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentChannelNotifyRecordCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentChannelNotifyRecordEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentChannelNotifyRecordStatus;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentNotificationTypes;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordStatus;
use Contena\Core\System\Payment\Gateway\GatewayNotificationHandlerInterface;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\GatewayNotification;
use Contena\Core\System\Payment\Struct\GatewayNotificationResponse;
use Contena\Core\System\Payment\Struct\GatewayNotificationResult;
use Contena\Core\System\Payment\Struct\PaymentNotificationTarget;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * @internal
 */
final class GatewayNotificationService
{
    /**
     * @param EntityRepository<PaymentChannelConfigCollection> $channelConfigRepository
     * @param EntityRepository<PaymentChannelNotifyRecordCollection> $channelNotifyRecordRepository
     * @param EntityRepository<PaymentNotifyRecordCollection> $notifyRecordRepository
     */
    public function __construct(
        private readonly EntityRepository $channelConfigRepository,
        private readonly EntityRepository $channelNotifyRecordRepository,
        private readonly EntityRepository $notifyRecordRepository,
        private readonly GatewayRegistry $gatewayRegistry,
        private readonly PaymentNotificationTargetResolver $targetResolver,
        private readonly PaymentOrderService $paymentOrderService,
        private readonly PaymentRefundService $paymentRefundService,
        private readonly PaymentTransferService $paymentTransferService,
        private readonly PaymentSubscriptionService $paymentSubscriptionService,
        private readonly Connection $connection,
    ) {
    }

    public function process(string $channel, string $channelConfigId, GatewayNotification $notification): GatewayNotificationResponse
    {
        $config = $this->loadConfig($channel, $channelConfigId);
        $notificationKey = $this->notificationKey($notification);
        $existing = $this->findRecord($channelConfigId, $notificationKey);
        if ($existing instanceof PaymentChannelNotifyRecordEntity && $existing->status === PaymentChannelNotifyRecordStatus::STATUS_PROCESSED) {
            return $this->responseFromRecord($existing);
        }

        $gateway = $this->gatewayRegistry->get($channel);
        if (!$gateway instanceof GatewayNotificationHandlerInterface) {
            throw PaymentException::capabilityNotSupported($channel, 'notification');
        }

        $result = $gateway->handleNotification($notification, $config->config ?? []);
        $response = new GatewayNotificationResponse($result->responseBody, $result->responseContentType, $result->responseStatus);
        $target = $this->targetResolver->resolve($result->type, $result->resourceNo);
        $recordId = $this->claimRecord($channel, $channelConfigId, $notificationKey, $notification, $result, $target, $existing);

        try {
            return $this->connection->transactional(function () use ($channel, $channelConfigId, $recordId, $result, $response, $target): GatewayNotificationResponse {
                $this->lockRecord($recordId);
                $record = $this->loadRecord($recordId, $target->context);
                if ($record->status === PaymentChannelNotifyRecordStatus::STATUS_PROCESSED) {
                    return $this->responseFromRecord($record);
                }

                $this->apply($channel, $channelConfigId, $result, $target);
                $this->channelNotifyRecordRepository->update([[
                    'id' => $recordId,
                    'responseBody' => $result->responseBody,
                    'responseContentType' => $result->responseContentType,
                    'responseStatus' => $result->responseStatus,
                    'status' => PaymentChannelNotifyRecordStatus::STATUS_PROCESSED,
                    'errorMessage' => null,
                ]], $target->context);

                if ($target->notifyUrl !== null && $target->notifyUrl !== '') {
                    $this->notifyRecordRepository->create([[
                        'id' => Uuid::randomHex(),
                        'notifyType' => $result->type,
                        'notifyUrl' => $target->notifyUrl,
                        'requestBody' => $this->outboundPayload($result, $target),
                        'status' => PaymentNotifyRecordStatus::STATUS_PENDING,
                        'retryCount' => 0,
                        ...$this->targetAssociation($target),
                    ]], $target->context);
                }

                return $response;
            });
        } catch (\Throwable $exception) {
            $this->markFailed($recordId, $target->context, $exception);

            throw $exception;
        }
    }

    private function claimRecord(
        string $channel,
        string $channelConfigId,
        string $notificationKey,
        GatewayNotification $notification,
        GatewayNotificationResult $result,
        PaymentNotificationTarget $target,
        ?PaymentChannelNotifyRecordEntity $existing,
    ): string {
        if ($existing instanceof PaymentChannelNotifyRecordEntity) {
            return $existing->getId();
        }

        $recordId = Uuid::randomHex();

        try {
            $this->channelNotifyRecordRepository->create([[
                'id' => $recordId,
                'channelCode' => $channel,
                'channelConfigId' => $channelConfigId,
                'notificationKey' => $notificationKey,
                'notifyType' => $result->type,
                'rawBody' => $this->storedPayload($notification),
                'status' => PaymentChannelNotifyRecordStatus::STATUS_PENDING,
                ...$this->targetAssociation($target),
            ]], $target->context);

            return $recordId;
        } catch (UniqueConstraintViolationException) {
            $record = $this->findRecord($channelConfigId, $notificationKey);
            if (!$record instanceof PaymentChannelNotifyRecordEntity) {
                throw PaymentException::invalidRequest('The payment notification could not be claimed.');
            }

            return $record->getId();
        }
    }

    private function loadConfig(string $channel, string $channelConfigId): PaymentChannelConfigEntity
    {
        $criteria = new Criteria([$channelConfigId]);
        $criteria->addAssociation('channel');
        $config = $this->channelConfigRepository->search($criteria, Context::createGlobalContext())->getEntities()->first();
        if (!$config instanceof PaymentChannelConfigEntity) {
            throw PaymentException::channelConfigNotFound($channelConfigId);
        }
        if (!$config->channel instanceof PaymentChannelEntity || $config->channel->code !== $channel) {
            throw PaymentException::notificationConfigurationMismatch($channelConfigId);
        }

        return $config;
    }

    private function findRecord(string $channelConfigId, string $notificationKey): ?PaymentChannelNotifyRecordEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('channelConfigId', $channelConfigId));
        $criteria->addFilter(new EqualsFilter('notificationKey', $notificationKey));
        $criteria->setLimit(1);
        $record = $this->channelNotifyRecordRepository->search($criteria, Context::createGlobalContext())->getEntities()->first();

        return $record instanceof PaymentChannelNotifyRecordEntity ? $record : null;
    }

    private function loadRecord(string $recordId, Context $context): PaymentChannelNotifyRecordEntity
    {
        $record = $this->channelNotifyRecordRepository->search(new Criteria([$recordId]), $context)->getEntities()->first();

        return $record instanceof PaymentChannelNotifyRecordEntity
            ? $record
            : throw PaymentException::invalidRequest('The payment notification record could not be loaded.');
    }

    private function lockRecord(string $recordId): void
    {
        $this->connection->fetchOne(
            'SELECT `id` FROM `payment_channel_notify_record` WHERE `id` = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($recordId)],
        );
    }

    private function markFailed(string $recordId, Context $context, \Throwable $exception): void
    {
        $this->connection->transactional(function () use ($recordId, $context, $exception): void {
            $this->lockRecord($recordId);
            $record = $this->loadRecord($recordId, $context);
            if ($record->status === PaymentChannelNotifyRecordStatus::STATUS_PROCESSED) {
                return;
            }

            $this->channelNotifyRecordRepository->update([[
                'id' => $recordId,
                'status' => PaymentChannelNotifyRecordStatus::STATUS_FAILED,
                'errorMessage' => mb_substr($exception->getMessage(), 0, 255),
            ]], $context);
        });
    }

    private function responseFromRecord(PaymentChannelNotifyRecordEntity $record): GatewayNotificationResponse
    {
        return new GatewayNotificationResponse(
            $record->responseBody ?? '',
            $record->responseContentType ?? 'text/plain',
            $record->responseStatus ?? 200,
        );
    }

    private function apply(string $channel, string $channelConfigId, GatewayNotificationResult $notification, PaymentNotificationTarget $target): void
    {
        match ($notification->type) {
            PaymentNotificationTypes::PAYMENT => $this->paymentOrderService->applyNotification($channel, $channelConfigId, $target, $notification->result),
            PaymentNotificationTypes::REFUND => $this->paymentRefundService->applyNotification($channel, $channelConfigId, $target, $notification->result),
            PaymentNotificationTypes::TRANSFER => $this->paymentTransferService->applyNotification($channel, $channelConfigId, $target, $notification->result),
            PaymentNotificationTypes::SUBSCRIPTION => $this->paymentSubscriptionService->applyNotification($channel, $channelConfigId, $target, $notification->result),
            default => throw PaymentException::invalidRequest('The payment notification type is not supported.'),
        };
    }

    /**
     * @return array<string, string>
     */
    private function targetAssociation(PaymentNotificationTarget $target): array
    {
        return match ($target->entityName) {
            'payment_order' => ['orderId' => $target->entityId],
            'payment_refund' => ['refundId' => $target->entityId],
            'payment_transfer' => ['transferId' => $target->entityId],
            'payment_recurring' => ['recurringId' => $target->entityId],
            default => throw PaymentException::invalidRequest('The payment notification target is not supported.'),
        };
    }

    private function notificationKey(GatewayNotification $notification): string
    {
        return Hasher::hash($this->storedPayload($notification), 'sha256');
    }

    private function storedPayload(GatewayNotification $notification): string
    {
        if ($notification->rawBody !== '') {
            return $notification->rawBody;
        }

        return json_encode($this->sortRecursively($notification->parameters), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $child) {
            $value[$key] = $this->sortRecursively($child);
        }
        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function outboundPayload(GatewayNotificationResult $notification, PaymentNotificationTarget $target): string
    {
        return json_encode([
            'type' => match ($notification->type) {
                PaymentNotificationTypes::PAYMENT => 'payment',
                PaymentNotificationTypes::REFUND => 'refund',
                PaymentNotificationTypes::TRANSFER => 'transfer',
                PaymentNotificationTypes::SUBSCRIPTION => 'subscription',
                default => 'unknown',
            },
            'resourceNo' => $notification->resourceNo,
            'externalResourceNo' => $target->externalResourceNo,
            'status' => $notification->result->status,
            'resultCode' => $notification->result->resultCode,
            'resultMessage' => $notification->result->resultMessage,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
