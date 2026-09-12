<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Notification;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataScope;
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
use Contena\Core\System\Payment\Event\GatewayNotificationProcessedEvent;
use Contena\Core\System\Payment\Gateway\GatewayNotificationHandlerInterface;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\Notification\Struct\GatewayNotification;
use Contena\Core\System\Payment\Notification\Struct\GatewayNotificationResponse;
use Contena\Core\System\Payment\Notification\Struct\GatewayNotificationResult;
use Contena\Core\System\Payment\Notification\Struct\PaymentNotificationTarget;
use Contena\Core\System\Payment\PaymentException;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
final class GatewayNotificationService
{
    /**
     * @param EntityRepository<PaymentChannelConfigCollection> $channelConfigRepository
     * @param EntityRepository<PaymentChannelNotifyRecordCollection> $channelNotifyRecordRepository
     */
    public function __construct(
        private readonly EntityRepository $channelConfigRepository,
        private readonly EntityRepository $channelNotifyRecordRepository,
        private readonly GatewayRegistry $gatewayRegistry,
        private readonly PaymentNotificationHandlerRegistry $handlerRegistry,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function process(string $channel, string $channelConfigId, GatewayNotification $notification): GatewayNotificationResponse
    {
        $config = $this->loadConfig($channel, $channelConfigId);
        $configContext = $this->contextForDataScope($config->dataScopeId);
        $notificationKey = Hasher::hash($this->storedPayload($notification), 'sha256');
        $existing = $this->findRecord($channelConfigId, $notificationKey, $configContext);
        if ($existing instanceof PaymentChannelNotifyRecordEntity && $existing->status === PaymentChannelNotifyRecordStatus::STATUS_PROCESSED) {
            return $this->responseFromRecord($existing);
        }

        $gateway = $this->gatewayRegistry->get($channel);
        if (!$gateway instanceof GatewayNotificationHandlerInterface) {
            throw PaymentException::capabilityNotSupported($channel, 'notification');
        }

        $result = $gateway->handleNotification($notification, $config->config ?? []);
        $response = new GatewayNotificationResponse($result->responseBody, $result->responseContentType, $result->responseStatus);
        $handler = $this->handlerRegistry->get($result->type);
        $target = $handler->resolve($result->resourceNo, $channelConfigId, $configContext);
        if ($target->context->getDataScopeId() !== $config->dataScopeId) {
            throw PaymentException::notificationConfigurationMismatch($channelConfigId);
        }
        $recordId = $this->claimRecord($channel, $channelConfigId, $notificationKey, $notification, $result, $target, $existing, $configContext);

        try {
            return $this->connection->transactional(function () use ($channel, $channelConfigId, $recordId, $result, $response, $target, $handler): GatewayNotificationResponse {
                $current = $this->lock($recordId, $target->context);
                $record = $this->loadRecord($recordId, $target->context);
                $record->assign($current);
                if ($record->status === PaymentChannelNotifyRecordStatus::STATUS_PROCESSED) {
                    return $this->responseFromRecord($record);
                }

                $handler->apply($channel, $channelConfigId, $target, $result->result);
                $this->channelNotifyRecordRepository->update([[
                    'id' => $recordId,
                    'responseBody' => $result->responseBody,
                    'responseContentType' => $result->responseContentType,
                    'responseStatus' => $result->responseStatus,
                    'status' => PaymentChannelNotifyRecordStatus::STATUS_PROCESSED,
                    'errorMessage' => null,
                ]], $target->context);

                $this->eventDispatcher->dispatch(new GatewayNotificationProcessedEvent($recordId, $result, $target));

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
        Context $configContext,
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
                $target->associationName => $target->entityId,
            ]], $target->context);

            return $recordId;
        } catch (UniqueConstraintViolationException) {
            $record = $this->findRecord($channelConfigId, $notificationKey, $configContext);
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

    private function findRecord(string $channelConfigId, string $notificationKey, Context $context): ?PaymentChannelNotifyRecordEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('channelConfigId', $channelConfigId));
        $criteria->addFilter(new EqualsFilter('notificationKey', $notificationKey));
        $criteria->setLimit(1);
        $record = $this->channelNotifyRecordRepository->search($criteria, $context)->getEntities()->first();

        return $record instanceof PaymentChannelNotifyRecordEntity ? $record : null;
    }

    private function contextForDataScope(string $dataScopeId): Context
    {
        return Context::createDefaultContext()->createWithDataScope(
            $dataScopeId === Defaults::PLATFORM_DATA_SCOPE
                ? DataScope::platform()
                : DataScope::tenant($dataScopeId),
        );
    }

    private function loadRecord(string $recordId, Context $context): PaymentChannelNotifyRecordEntity
    {
        $record = $this->channelNotifyRecordRepository->search(new Criteria([$recordId]), $context)->getEntities()->first();

        return $record instanceof PaymentChannelNotifyRecordEntity
            ? $record
            : throw PaymentException::invalidRequest('The payment notification record could not be loaded.');
    }

    private function markFailed(string $recordId, Context $context, \Throwable $exception): void
    {
        $this->connection->transactional(function () use ($recordId, $context, $exception): void {
            $current = $this->lock($recordId, $context);
            if ($current['status'] === PaymentChannelNotifyRecordStatus::STATUS_PROCESSED) {
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

    /**
     * @return array<string, mixed>
     */
    private function lock(string $id, Context $context): array
    {
        if ($context->allowsCrossScopeReads()) {
            throw PaymentException::invalidRequest('Payment writes require an exact data-scope context.');
        }
        $row = $this->connection->fetchAssociative(
            'SELECT `status`, `response_body` AS `responseBody`, `response_content_type` AS `responseContentType`, `response_status` AS `responseStatus`
             FROM `payment_channel_notify_record`
             WHERE `id` = :id AND `data_scope_id` = :dataScopeId
             FOR UPDATE',
            [
                'id' => Uuid::fromHexToBytes($id),
                'dataScopeId' => Uuid::fromHexToBytes($context->getDataScopeId()),
            ],
        );
        if ($row === false) {
            throw PaymentException::notificationResourceNotFound($id);
        }

        $row['status'] = (int) $row['status'];
        $row['responseStatus'] = $row['responseStatus'] === null ? null : (int) $row['responseStatus'];

        return $row;
    }
}
