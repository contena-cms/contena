<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Subscription;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringStatus;
use Contena\Core\System\Payment\Event\PaymentStatusChangedEvent;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\GatewayResult;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Applies gateway outcomes to subscription agreements.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
final class PaymentSubscriptionStateHandler
{
    /**
     * @param EntityRepository<PaymentRecurringCollection> $paymentRecurringRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentRecurringRepository,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function apply(PaymentRecurringEntity $subscription, GatewayResult $result, Context $context): GatewayResult
    {
        return $this->connection->transactional(function () use ($subscription, $result, $context): GatewayResult {
            $current = $this->lock($subscription->getId(), $context);
            $subscription->assign([
                'status' => (int) $current['status'],
                'channelRecurringNo' => $current['channel_recurring_no'],
                'signTime' => \is_string($current['sign_time']) ? new \DateTimeImmutable($current['sign_time']) : null,
            ]);
            $status = match ($result->status) {
                PaymentStatus::SUCCEEDED => PaymentRecurringStatus::STATUS_SIGNED,
                PaymentStatus::CLOSED => PaymentRecurringStatus::STATUS_UNSIGNED,
                PaymentStatus::FAILED => PaymentRecurringStatus::STATUS_FAILED,
                default => PaymentRecurringStatus::STATUS_PENDING,
            };
            if ($subscription->status !== PaymentRecurringStatus::STATUS_PENDING
                && !($subscription->status === PaymentRecurringStatus::STATUS_SIGNED && $status === PaymentRecurringStatus::STATUS_UNSIGNED)
            ) {
                return GatewayResult::fromArray(
                    \is_array($current['response_data']) ? $current['response_data'] : null,
                    match ($subscription->status) {
                        PaymentRecurringStatus::STATUS_SIGNED => PaymentStatus::SUCCEEDED,
                        PaymentRecurringStatus::STATUS_UNSIGNED => PaymentStatus::CLOSED,
                        default => PaymentStatus::FAILED,
                    },
                );
            }

            $this->paymentRecurringRepository->update([[
                'id' => $subscription->getId(),
                'channelRecurringNo' => $result->response->resourceId ?? $subscription->channelRecurringNo,
                'status' => $status,
                'signTime' => $status === PaymentRecurringStatus::STATUS_SIGNED ? ($subscription->signTime ?? $this->clock->now()) : $subscription->signTime,
                'responseData' => $result->toArray(),
                'resultCode' => $result->response->code,
                'resultMessage' => $result->response->message,
            ]], $context);
            if ($subscription->status !== $status) {
                $this->eventDispatcher->dispatch(new PaymentStatusChangedEvent(
                    new PaymentEntityReference(PaymentRecurringDefinition::ENTITY_NAME, $subscription->getId()),
                    $context,
                    $result,
                ));
            }

            return $result;
        });
    }

    public function recordFailure(PaymentRecurringEntity $subscription, \Throwable $exception, Context $context): void
    {
        $this->connection->transactional(function () use ($subscription, $exception, $context): void {
            $current = $this->lock($subscription->getId(), $context);
            if ((int) $current['status'] !== PaymentRecurringStatus::STATUS_PENDING) {
                return;
            }
            $this->paymentRecurringRepository->update([[
                'id' => $subscription->getId(),
                'resultMessage' => mb_substr($exception->getMessage(), 0, 255),
            ]], $context);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function lock(string $id, Context $context): array
    {
        if ($context->hasGlobalTenantAccess()) {
            throw PaymentException::invalidRequest('Payment writes require a platform or tenant context.');
        }
        $parameters = ['id' => Uuid::fromHexToBytes($id)];
        $scope = '`tenant_id` IS NULL';
        if ($context->getTenantId() !== null) {
            $scope = '`tenant_id` = :tenantId';
            $parameters['tenantId'] = Uuid::fromHexToBytes($context->getTenantId());
        }
        $row = $this->connection->fetchAssociative('SELECT `status`, `response_data`, `channel_recurring_no`, `sign_time` FROM `payment_recurring` WHERE `id` = :id AND ' . $scope . ' FOR UPDATE', $parameters);
        if ($row === false) {
            throw PaymentException::notificationResourceNotFound($id);
        }
        if (\is_string($row['response_data'])) {
            $row['response_data'] = json_decode($row['response_data'], true, 512, \JSON_THROW_ON_ERROR);
        }

        return $row;
    }
}
