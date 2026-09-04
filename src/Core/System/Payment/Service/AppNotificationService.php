<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordStatus;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\OpenApi\OpenApiException;
use Contena\Core\System\Payment\OpenApi\Util\SignUtil;
use Contena\Tests\Integration\Core\System\Payment\Service\AppNotificationServiceTest;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Delivers provider-neutral payment status notifications to applications.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see AppNotificationServiceTest
 */
final class AppNotificationService
{
    private const int BATCH_SIZE = 100;

    private const int LEASE_SECONDS = 60;

    private const int MAX_RETRIES = 7;

    private const array RETRY_DELAYS = [60, 300, 900, 1800, 3600, 10800, 21600];

    /**
     * @param EntityRepository<PaymentNotifyRecordCollection> $notifyRecordRepository
     */
    public function __construct(
        private readonly EntityRepository $notifyRecordRepository,
        private readonly Connection $connection,
        private readonly HttpClientInterface $httpClient,
        private readonly ClockInterface $clock,
    ) {
    }

    public function deliverPending(Context $context): int
    {
        if ($context->hasGlobalTenantAccess()) {
            throw OpenApiException::invalidRequest('App notifications must be delivered with a platform or tenant context.');
        }

        $delivered = 0;
        foreach ($this->loadDueRecords($context) as $record) {
            if (!$this->claim($record->getId(), $context)) {
                continue;
            }

            $this->deliver($record, $context);
            ++$delivered;
        }

        return $delivered;
    }

    /**
     * @return iterable<PaymentNotifyRecordEntity>
     */
    private function loadDueRecords(Context $context): iterable
    {
        $now = $this->format($this->clock->now());
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('status', [
            PaymentNotifyRecordStatus::STATUS_PENDING,
            PaymentNotifyRecordStatus::STATUS_PROCESSING,
        ]));
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('availableAt', null),
            new RangeFilter('availableAt', [RangeFilter::LTE => $now]),
        ]));
        $criteria->addSorting(new FieldSorting('createdAt'));
        $criteria->setLimit(self::BATCH_SIZE);
        $criteria->addAssociations([
            'order.app',
            'refund.order.app',
            'transfer.app',
            'recurring.app',
        ]);

        return $this->notifyRecordRepository->search($criteria, $context)->getEntities();
    }

    private function claim(string $recordId, Context $context): bool
    {
        $now = $this->clock->now();
        $tenantId = $context->getTenantId();
        $tenantCondition = '`tenant_id` IS NULL';
        $parameters = [
            'id' => Uuid::fromHexToBytes($recordId),
            'pending' => PaymentNotifyRecordStatus::STATUS_PENDING,
            'processing' => PaymentNotifyRecordStatus::STATUS_PROCESSING,
            'now' => $this->format($now),
            'leaseUntil' => $this->format($now->modify('+' . self::LEASE_SECONDS . ' seconds')),
        ];

        if ($tenantId !== null) {
            $tenantCondition = '`tenant_id` = :tenantId';
            $parameters['tenantId'] = Uuid::fromHexToBytes($tenantId);
        }

        return $this->connection->executeStatement(
            \sprintf(
                'UPDATE `payment_notify_record`
                    SET `status` = :processing, `available_at` = :leaseUntil, `updated_at` = :now
                    WHERE `id` = :id AND %s
                      AND `status` IN (:pending, :processing)
                      AND (`available_at` IS NULL OR `available_at` <= :now)',
                $tenantCondition,
            ),
            $parameters,
        ) === 1;
    }

    private function deliver(PaymentNotifyRecordEntity $record, Context $context): void
    {
        $requestBody = $record->requestBody ?? '';

        try {
            $app = $this->app($record);
            $payload = $this->payload($record, $app);
            $requestBody = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
            $response = $this->httpClient->request('POST', $record->notifyUrl, [
                'headers' => ['Accept' => 'application/json'],
                'json' => $payload,
                'max_duration' => 15,
                'max_redirects' => 0,
                'timeout' => 10,
            ]);
            $responseStatus = $response->getStatusCode();
            $responseBody = $response->getContent(false);

            if ($responseStatus >= 200 && $responseStatus < 300) {
                $this->notifyRecordRepository->update([[
                    'id' => $record->getId(),
                    'requestBody' => $requestBody,
                    'responseBody' => $responseBody,
                    'responseStatus' => $responseStatus,
                    'status' => PaymentNotifyRecordStatus::STATUS_SUCCEEDED,
                    'availableAt' => null,
                ]], $context);

                return;
            }

            $this->markFailedAttempt($record, $context, $requestBody, $responseBody, $responseStatus);
        } catch (\Throwable $exception) {
            $this->markFailedAttempt($record, $context, $requestBody, $exception->getMessage(), null);
        }
    }

    private function app(PaymentNotifyRecordEntity $record): PaymentAppEntity
    {
        if ($record->order instanceof PaymentOrderEntity && $record->order->app instanceof PaymentAppEntity) {
            return $record->order->app;
        }
        if ($record->refund instanceof PaymentRefundEntity && $record->refund->order instanceof PaymentOrderEntity && $record->refund->order->app instanceof PaymentAppEntity) {
            return $record->refund->order->app;
        }
        if ($record->transfer instanceof PaymentTransferEntity && $record->transfer->app instanceof PaymentAppEntity) {
            return $record->transfer->app;
        }
        if ($record->recurring instanceof PaymentRecurringEntity && $record->recurring->app instanceof PaymentAppEntity) {
            return $record->recurring->app;
        }

        throw OpenApiException::invalidRequest('The payment app for the notification could not be loaded.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PaymentNotifyRecordEntity $record, PaymentAppEntity $app): array
    {
        $payload = json_decode($record->requestBody ?? '', true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($payload) || array_is_list($payload)) {
            throw OpenApiException::invalidRequest('The app notification payload must be a JSON object.');
        }

        $payload['app_id'] = $app->appCode;
        $payload['notification_id'] = $record->getId();
        $payload['timestamp'] = (string) $this->clock->now()->getTimestamp();
        $payload['nonce'] = Uuid::randomHex();
        $payload['sign'] = SignUtil::sign($payload, $app->appSecret);

        return $payload;
    }

    private function markFailedAttempt(
        PaymentNotifyRecordEntity $record,
        Context $context,
        string $requestBody,
        string $responseBody,
        ?int $responseStatus,
    ): void {
        $update = [
            'id' => $record->getId(),
            'requestBody' => $requestBody,
            'responseBody' => $responseBody,
            'responseStatus' => $responseStatus,
            'availableAt' => null,
        ];

        if ($record->retryCount >= self::MAX_RETRIES) {
            $update['status'] = PaymentNotifyRecordStatus::STATUS_FAILED;
        } else {
            $update['status'] = PaymentNotifyRecordStatus::STATUS_PENDING;
            $update['retryCount'] = $record->retryCount + 1;
            $update['availableAt'] = $this->format($this->clock->now()->modify('+' . self::RETRY_DELAYS[$record->retryCount] . ' seconds'));
        }

        $this->notifyRecordRepository->update([$update], $context);
    }

    private function format(\DateTimeInterface $dateTime): string
    {
        return \DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
