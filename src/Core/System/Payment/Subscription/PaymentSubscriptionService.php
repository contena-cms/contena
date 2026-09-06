<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Subscription;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringStatus;
use Contena\Core\System\Payment\Gateway\GatewayOperationExecutor;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\SubscriptionHandlerInterface;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Routing\PaymentRoutingRequest;
use Contena\Core\System\Payment\Struct\GatewayResult;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Subscription\Struct\SubscriptionRequest;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
class PaymentSubscriptionService extends AbstractPaymentSubscriptionService
{
    /**
     * @param EntityRepository<PaymentRecurringCollection> $paymentRecurringRepository
     */
    public function __construct(
        private readonly PaymentSubscriptionPersister $persister,
        private readonly PaymentSubscriptionStateHandler $stateHandler,
        private readonly EntityRepository $paymentRecurringRepository,
        private readonly AbstractPaymentRouteResolver $routeResolver,
        private readonly GatewayOperationExecutor $gatewayExecutor,
    ) {
    }

    public function getDecorated(): AbstractPaymentSubscriptionService
    {
        throw new DecorationPatternException(self::class);
    }

    public function subscribe(PaymentAppEntity $app, SubscriptionRequest $request, Context $context): PaymentResult
    {
        if (!$app->status || $app->tenantId !== $context->getTenantId()) {
            throw PaymentException::appNotFound($app->appCode);
        }
        if ($context->hasGlobalTenantAccess()) {
            throw PaymentException::invalidRequest('Payment operations require a platform or tenant context.');
        }

        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('paymentAppId', $app->getId()))
            ->addFilter(new EqualsFilter('externalRecurringNo', $request->externalSubscriptionNo))
            ->setLimit(1);
        $existingSubscription = $this->paymentRecurringRepository->search($criteria, $context)->getEntities()->first();
        if ($existingSubscription instanceof PaymentRecurringEntity) {
            if ($existingSubscription->periodType !== $request->periodType
                || $existingSubscription->period !== $request->period
                || $existingSubscription->executeTime?->getTimestamp() !== $request->executeTime?->getTimestamp()
                || $existingSubscription->singleAmount !== $request->singleAmount
                || $existingSubscription->totalAmount !== $request->totalAmount
                || $existingSubscription->totalPayments !== $request->totalPayments
            ) {
                throw PaymentException::duplicateReference($request->externalSubscriptionNo);
            }
            $status = match ($existingSubscription->status) {
                PaymentRecurringStatus::STATUS_SIGNED => PaymentStatus::SUCCEEDED,
                PaymentRecurringStatus::STATUS_FAILED => PaymentStatus::FAILED,
                PaymentRecurringStatus::STATUS_UNSIGNED => PaymentStatus::CLOSED,
                default => PaymentStatus::PENDING,
            };

            return new PaymentResult(
                $existingSubscription->recurringNo,
                $existingSubscription->externalRecurringNo,
                GatewayResult::fromArray($existingSubscription->responseData, $status),
            );
        }

        $route = $this->routeResolver->resolve($app, $context, new PaymentRoutingRequest(PaymentOperation::SUBSCRIBE, SubscriptionHandlerInterface::class, preferredChannel: $request->channel));
        if (!$route->gateway instanceof SubscriptionHandlerInterface) {
            throw PaymentException::capabilityNotSupported($route->gateway->code(), PaymentOperation::SUBSCRIBE);
        }

        $subscriptionId = $this->persister->persist([
            'paymentAppId' => $app->getId(),
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
        ], $context);
        $subscription = $this->paymentRecurringRepository->search(new Criteria([$subscriptionId]), $context)->getEntities()->first()
            ?? throw PaymentException::subscriptionNotFound($subscriptionId);
        try {
            $gatewayResult = $this->gatewayExecutor->execute(
                PaymentOperation::SUBSCRIBE,
                new PaymentEntityReference(PaymentRecurringDefinition::ENTITY_NAME, $subscription->getId()),
                $route,
                $context,
                fn (): GatewayResult => $route->gateway->subscribe($subscription, $route->config),
            );
        } catch (\Throwable $exception) {
            $this->stateHandler->recordFailure($subscription, $exception, $context);
            throw $exception;
        }

        $gatewayResult = $this->stateHandler->apply($subscription, $gatewayResult, $context);

        return new PaymentResult($subscription->recurringNo, $subscription->externalRecurringNo, $gatewayResult);
    }
}
