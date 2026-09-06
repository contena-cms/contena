<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Subscription;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringStatus;
use Contena\Core\System\Payment\Gateway\GatewayOperationExecutor;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\SubscriptionHandlerInterface;
use Contena\Core\System\Payment\PaymentAppGuard;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Routing\PaymentRoutingRequest;
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
    public function __construct(
        private readonly PaymentSubscriptionPersister $persister,
        private readonly AbstractPaymentRouteResolver $routeResolver,
        private readonly GatewayOperationExecutor $gatewayExecutor,
        private readonly PaymentAppGuard $appGuard,
    ) {
    }

    public function getDecorated(): AbstractPaymentSubscriptionService
    {
        throw new DecorationPatternException(self::class);
    }

    public function subscribe(PaymentAppEntity $app, SubscriptionRequest $request, Context $context): PaymentResult
    {
        $this->appGuard->validate($app, $context);

        $existingSubscription = $this->persister->findSubscription($app->getId(), $request->externalSubscriptionNo, $context);
        if ($existingSubscription instanceof PaymentRecurringEntity) {
            $this->assertSubscriptionRequestMatchesEntity($existingSubscription, $request);

            return $this->createResultForSubscription($existingSubscription);
        }

        $route = $this->routeResolver->resolve($app, $context, new PaymentRoutingRequest(PaymentOperation::SUBSCRIBE, SubscriptionHandlerInterface::class, preferredChannel: $request->channel));
        if (!$route->gateway instanceof SubscriptionHandlerInterface) {
            throw PaymentException::capabilityNotSupported($route->gateway->code(), PaymentOperation::SUBSCRIBE);
        }

        $subscriptionId = $this->persister->createSubscription($app, $request, $route, $context);

        $subscription = $this->persister->getSubscriptionById($subscriptionId, $context);
        try {
            $result = $this->gatewayExecutor->execute(PaymentOperation::SUBSCRIBE, $this->persister->reference($subscription), $route, $context, fn (): PaymentResult => $route->gateway->subscribe($subscription, $route->config));
        } catch (\Throwable $exception) {
            $this->persister->recordFailure($subscription, $exception, $context);
            throw $exception;
        }

        $result = $this->persister->persistResult($subscription, $result, $context);

        return $result->withResource($subscription->recurringNo, $subscription->externalRecurringNo);
    }

    private function createResultForSubscription(PaymentRecurringEntity $subscription): PaymentResult
    {
        $status = match ($subscription->status) {
            PaymentRecurringStatus::STATUS_SIGNED => PaymentStatus::SUCCEEDED,
            PaymentRecurringStatus::STATUS_FAILED => PaymentStatus::FAILED,
            PaymentRecurringStatus::STATUS_UNSIGNED => PaymentStatus::CLOSED,
            default => PaymentStatus::PENDING,
        };

        return PaymentResult::fromArray($subscription->responseData, $status)
            ->withResource($subscription->recurringNo, $subscription->externalRecurringNo);
    }

    private function assertSubscriptionRequestMatchesEntity(PaymentRecurringEntity $subscription, SubscriptionRequest $request): void
    {
        if ($subscription->periodType !== $request->periodType
            || $subscription->period !== $request->period
            || $subscription->executeTime?->getTimestamp() !== $request->executeTime?->getTimestamp()
            || $subscription->singleAmount !== $request->singleAmount
            || $subscription->totalAmount !== $request->totalAmount
            || $subscription->totalPayments !== $request->totalPayments
        ) {
            throw PaymentException::duplicateReference($request->externalSubscriptionNo);
        }
    }
}
