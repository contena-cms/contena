<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

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
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\SubscribeHandlerInterface;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Rule\PaymentRuleScope;
use Contena\Core\System\Payment\Struct\PaymentNotificationTarget;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\PaymentRoute;
use Contena\Core\System\Payment\Struct\SubscriptionRequest;
use Psr\Clock\ClockInterface;

/**
 * @internal
 */
final class PaymentSubscriptionService
{
    /**
     * @param EntityRepository<PaymentRecurringCollection> $paymentRecurringRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentRecurringRepository,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
        private readonly AbstractPaymentRouteResolver $routeResolver,
        private readonly ClockInterface $clock,
    ) {
    }

    public function subscribe(PaymentAppEntity $app, SubscriptionRequest $request, Context $context): PaymentResult
    {
        if ($request->externalSubscriptionNo === '') {
            throw PaymentException::invalidRequest('External subscription number is required.');
        }

        $existing = $this->findSubscription($app->getId(), $request->externalSubscriptionNo, $context);
        if ($existing instanceof PaymentRecurringEntity) {
            $this->assertSameSubscription($existing, $request);

            return $this->resultFromSubscription($existing);
        }

        $route = $this->routeResolver->resolve(new PaymentRuleScope(
            $context,
            $app,
            PaymentOperation::SUBSCRIBE,
            preferredChannel: $request->channel,
            amount: $request->singleAmount,
            data: $request->extra,
        ));
        if (!$route->gateway instanceof SubscribeHandlerInterface) {
            throw PaymentException::capabilityNotSupported($route->gateway->code(), PaymentOperation::SUBSCRIBE);
        }

        $subscriptionId = $this->createSubscription($app, $request, $route, $context);

        $subscription = $this->loadSubscription($subscriptionId, $context);
        try {
            $result = $route->gateway->subscribe($subscription, $route->config);
        } catch (\Throwable $exception) {
            $this->paymentRecurringRepository->update([[
                'id' => $subscription->getId(),
                'resultMessage' => $exception->getMessage(),
            ]], $context);
            throw $exception;
        }

        $status = $this->subscriptionStatus($result->status);
        $this->paymentRecurringRepository->update([[
            'id' => $subscription->getId(),
            'channelRecurringNo' => $result->providerResourceId,
            'status' => $status,
            'signTime' => $status === PaymentRecurringStatus::STATUS_SIGNED ? $this->clock->now() : null,
            'responseData' => $result->toArray(),
            'resultCode' => $result->resultCode,
            'resultMessage' => $result->resultMessage,
        ]], $context);

        return $result->withResource($subscription->recurringNo, $subscription->externalRecurringNo);
    }

    public function applyNotification(string $channel, string $channelConfigId, PaymentNotificationTarget $target, PaymentResult $result): void
    {
        $subscription = $this->loadSubscription($target->entityId, $target->context);
        if ($subscription->channelCode !== $channel || $subscription->channelConfigId !== $channelConfigId) {
            throw PaymentException::notificationConfigurationMismatch($subscription->recurringNo);
        }

        $status = match ($result->status) {
            PaymentStatus::SUCCEEDED => PaymentRecurringStatus::STATUS_SIGNED,
            PaymentStatus::CLOSED => PaymentRecurringStatus::STATUS_UNSIGNED,
            PaymentStatus::FAILED => PaymentRecurringStatus::STATUS_FAILED,
            default => PaymentRecurringStatus::STATUS_PENDING,
        };
        if ($subscription->status === PaymentRecurringStatus::STATUS_PENDING || ($subscription->status === PaymentRecurringStatus::STATUS_SIGNED && $status === PaymentRecurringStatus::STATUS_UNSIGNED)) {
            $this->paymentRecurringRepository->update([[
                'id' => $subscription->getId(),
                'channelRecurringNo' => $result->providerResourceId,
                'status' => $status,
                'signTime' => $status === PaymentRecurringStatus::STATUS_SIGNED ? $this->clock->now() : $subscription->signTime,
                'responseData' => $result->toArray(),
                'resultCode' => $result->resultCode,
                'resultMessage' => $result->resultMessage,
            ]], $target->context);
        }
    }

    private function subscriptionStatus(string $status): int
    {
        return match ($status) {
            PaymentStatus::SUCCEEDED => PaymentRecurringStatus::STATUS_SIGNED,
            PaymentStatus::FAILED, PaymentStatus::CLOSED => PaymentRecurringStatus::STATUS_FAILED,
            default => PaymentRecurringStatus::STATUS_PENDING,
        };
    }

    private function findSubscription(string $appId, string $externalSubscriptionNo, Context $context): ?PaymentRecurringEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $appId));
        $criteria->addFilter(new EqualsFilter('externalRecurringNo', $externalSubscriptionNo));
        $criteria->setLimit(1);
        $subscription = $this->paymentRecurringRepository->search($criteria, $context)->getEntities()->first();

        return $subscription instanceof PaymentRecurringEntity ? $subscription : null;
    }

    private function loadSubscription(string $subscriptionId, Context $context): PaymentRecurringEntity
    {
        $subscription = $this->paymentRecurringRepository->search(new Criteria([$subscriptionId]), $context)->getEntities()->first();

        return $subscription instanceof PaymentRecurringEntity ? $subscription : throw PaymentException::invalidRequest('Payment subscription could not be loaded.');
    }

    private function resultFromSubscription(PaymentRecurringEntity $subscription): PaymentResult
    {
        $status = match ($subscription->status) {
            PaymentRecurringStatus::STATUS_SIGNED => PaymentStatus::SUCCEEDED,
            PaymentRecurringStatus::STATUS_FAILED => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };

        return PaymentResult::fromArray($subscription->responseData, $status)
            ->withResource($subscription->recurringNo, $subscription->externalRecurringNo);
    }

    private function assertSameSubscription(PaymentRecurringEntity $subscription, SubscriptionRequest $request): void
    {
        if ($subscription->periodType !== $request->periodType
            || $subscription->period !== $request->period
            || $this->timestamp($subscription->executeTime) !== $this->timestamp($request->executeTime)
            || $subscription->singleAmount !== $request->singleAmount
            || $subscription->totalAmount !== $request->totalAmount
            || $subscription->totalPayments !== $request->totalPayments
        ) {
            throw PaymentException::duplicateReference($request->externalSubscriptionNo);
        }
    }

    private function timestamp(?\DateTimeInterface $date): ?int
    {
        return $date?->getTimestamp();
    }

    private function createSubscription(PaymentAppEntity $app, SubscriptionRequest $request, PaymentRoute $route, Context $context): string
    {
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

        return $subscriptionId;
    }
}
