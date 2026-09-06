<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Subscription;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringStatus;
use Contena\Core\System\Payment\Event\PaymentEntityCreatedEvent;
use Contena\Core\System\Payment\Event\PaymentResultAppliedEvent;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Notification\Struct\PaymentNotificationTarget;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\PaymentRoute;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Subscription\Struct\SubscriptionRequest;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
class PaymentSubscriptionPersister
{
    /**
     * @param EntityRepository<PaymentRecurringCollection> $paymentRecurringRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentRecurringRepository,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function applyNotification(string $channel, string $channelConfigId, PaymentNotificationTarget $target, PaymentResult $result): void
    {
        $subscription = $this->getSubscriptionById($target->entityId, $target->context);
        if ($subscription->channelCode !== $channel || $subscription->channelConfigId !== $channelConfigId) {
            throw PaymentException::notificationConfigurationMismatch($subscription->recurringNo);
        }

        $this->persistResult($subscription, $result, $target->context);
    }

    public function persistResult(PaymentRecurringEntity $subscription, PaymentResult $result, Context $context): PaymentResult
    {
        return $this->connection->transactional(function () use ($subscription, $result, $context): PaymentResult {
            $current = $this->lock($subscription->getId(), $context);
            $subscription = $this->getSubscriptionById($subscription->getId(), $context);
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
                return PaymentResult::fromArray(\is_array($current['response_data']) ? $current['response_data'] : null, match ($subscription->status) {
                    PaymentRecurringStatus::STATUS_SIGNED => PaymentStatus::SUCCEEDED, PaymentRecurringStatus::STATUS_UNSIGNED => PaymentStatus::CLOSED, default => PaymentStatus::FAILED,
                });
            }

            $this->paymentRecurringRepository->update([[
                'id' => $subscription->getId(),
                'channelRecurringNo' => $result->providerResourceId ?? $subscription->channelRecurringNo,
                'status' => $status,
                'signTime' => $status === PaymentRecurringStatus::STATUS_SIGNED ? ($subscription->signTime ?? $this->clock->now()) : $subscription->signTime,
                'responseData' => $result->toArray(),
                'resultCode' => $result->resultCode,
                'resultMessage' => $result->resultMessage,
            ]], $context);
            if ($subscription->status !== $status) {
                $this->eventDispatcher->dispatch(new PaymentResultAppliedEvent($this->reference($subscription), $context, $result));
            }

            return $result;
        });
    }

    public function findSubscription(string $appId, string $externalSubscriptionNo, Context $context): ?PaymentRecurringEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $appId));
        $criteria->addFilter(new EqualsFilter('externalRecurringNo', $externalSubscriptionNo));
        $criteria->setLimit(1);
        $subscription = $this->paymentRecurringRepository->search($criteria, $context)->getEntities()->first();

        return $subscription;
    }

    public function getSubscriptionById(string $subscriptionId, Context $context): PaymentRecurringEntity
    {
        $subscription = $this->paymentRecurringRepository->search(new Criteria([$subscriptionId]), $context)->getEntities()->first();

        return $subscription ?? throw PaymentException::subscriptionNotFound($subscriptionId);
    }

    public function createSubscription(PaymentAppEntity $app, SubscriptionRequest $request, PaymentRoute $route, Context $context): string
    {
        return $this->connection->transactional(function () use ($app, $request, $route, $context): string {
            $subscriptionId = Uuid::randomHex();

            $this->paymentRecurringRepository->create([[
                'id' => $subscriptionId,
                'paymentAppId' => $app->getId(),
                'recurringNo' => $this->numberRangeValueGenerator->getValue(PaymentRecurringDefinition::ENTITY_NAME, $context),
                'externalRecurringNo' => $request->externalSubscriptionNo,
                'channelCode' => $route->gateway->code(),
                'channelConfigId' => $route->channelConfigId,
                'channelExtra' => $request->extra,
                'notifyUrl' => $request->notifyUrl,
                'returnUrl' => $request->returnUrl,
                'periodType' => $request->periodType,
                'period' => $request->period,
                'executeTime' => $request->executeTime,
                'singleAmount' => $request->singleAmount,
                'totalAmount' => $request->totalAmount,
                'totalPayments' => $request->totalPayments,
                'status' => PaymentRecurringStatus::STATUS_PENDING,
            ]], $context);

            $this->eventDispatcher->dispatch(new PaymentEntityCreatedEvent($this->reference($this->getSubscriptionById($subscriptionId, $context)), $context));

            return $subscriptionId;
        });
    }

    public function reference(PaymentRecurringEntity $entity): PaymentEntityReference
    {
        return new PaymentEntityReference(PaymentRecurringDefinition::ENTITY_NAME, $entity->getId());
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
     * The locking read, unlike a DAL snapshot read, sees the latest committed row.
     *
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
