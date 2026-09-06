<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\Event\PaymentGatewayCompletedEvent;
use Contena\Core\System\Payment\Event\PaymentGatewayFailedEvent;
use Contena\Core\System\Payment\Event\PaymentGatewayStartedEvent;
use Contena\Core\System\Payment\Routing\PaymentRoute;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Observable provider calls, without implicit retries or database transactions.
 *
 * @final
 */
class GatewayOperationExecutor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param callable(): PaymentResult $call
     */
    public function execute(string $operation, PaymentEntityReference $entity, PaymentRoute $route, Context $context, callable $call): PaymentResult
    {
        try {
            $this->eventDispatcher->dispatch(new PaymentGatewayStartedEvent($operation, $entity, $route->gateway->code(), $route->channelConfigId, $context));
            $result = $call();
        } catch (\Throwable $exception) {
            $this->observe(new PaymentGatewayFailedEvent($operation, $entity, $route->gateway->code(), $route->channelConfigId, $context, $exception));

            throw $exception;
        }

        $this->observe(new PaymentGatewayCompletedEvent($operation, $entity, $route->gateway->code(), $route->channelConfigId, $context, $result));

        return $result;
    }

    private function observe(PaymentGatewayCompletedEvent|PaymentGatewayFailedEvent $event): void
    {
        try {
            $this->eventDispatcher->dispatch($event);
        } catch (\Throwable $exception) {
            // Observer failure must not discard a provider response or mask its exception.
            $this->logger->error('Payment gateway observer failed.', ['event' => $event::class, 'exception' => $exception, 'tenantId' => $event->getContext()->getTenantId()]);
        }
    }
}
