<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Transfer;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferStates;
use Contena\Core\System\Payment\Gateway\GatewayOperationExecutor;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\TransferHandlerInterface;
use Contena\Core\System\Payment\PaymentAppGuard;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Routing\PaymentRoutingRequest;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Transfer\Struct\TransferRequest;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
class PaymentTransferService extends AbstractPaymentTransferService
{
    public function __construct(
        private readonly PaymentTransferPersister $persister,
        private readonly AbstractPaymentRouteResolver $routeResolver,
        private readonly GatewayOperationExecutor $gatewayExecutor,
        private readonly PaymentAppGuard $appGuard,
    ) {
    }

    public function getDecorated(): AbstractPaymentTransferService
    {
        throw new DecorationPatternException(self::class);
    }

    public function transfer(PaymentAppEntity $app, TransferRequest $request, Context $context): PaymentResult
    {
        $this->appGuard->validate($app, $context);

        $existingTransfer = $this->persister->findTransfer($app->getId(), $request->externalTransferNo, $context);
        if ($existingTransfer instanceof PaymentTransferEntity) {
            $this->assertTransferRequestMatchesEntity($existingTransfer, $request);

            return $this->createResultForTransfer($existingTransfer);
        }

        $route = $this->routeResolver->resolve($app, $context, new PaymentRoutingRequest(PaymentOperation::TRANSFER, TransferHandlerInterface::class, preferredChannel: $request->channel, amount: $request->amount, currencyCode: $request->currencyCode));
        if (!$route->gateway instanceof TransferHandlerInterface) {
            throw PaymentException::capabilityNotSupported($route->gateway->code(), PaymentOperation::TRANSFER);
        }

        $transferId = $this->persister->createTransfer($app, $request, $route, $context);

        $transfer = $this->persister->getTransferById($transferId, $context);
        try {
            $result = $this->gatewayExecutor->execute(PaymentOperation::TRANSFER, $this->persister->reference($transfer), $route, $context, fn (): PaymentResult => $route->gateway->transfer($transfer, $route->config));
        } catch (\Throwable $exception) {
            $this->persister->recordFailure($transfer, $exception, $context);
            throw $exception;
        }

        $result = $this->persister->persistResult($transfer, $result, $context);

        return $result->withResource($transfer->transferNo, $transfer->externalTransferNo);
    }

    private function createResultForTransfer(PaymentTransferEntity $transfer): PaymentResult
    {
        $status = match ($transfer->state?->getTechnicalName()) {
            PaymentTransferStates::STATE_SUCCEEDED => PaymentStatus::SUCCEEDED,
            PaymentTransferStates::STATE_FAILED => PaymentStatus::FAILED,
            default => PaymentStatus::PROCESSING,
        };

        return PaymentResult::fromArray($transfer->responseData, $status)
            ->withResource($transfer->transferNo, $transfer->externalTransferNo);
    }

    private function assertTransferRequestMatchesEntity(PaymentTransferEntity $transfer, TransferRequest $request): void
    {
        if ($transfer->amount !== $request->amount
            || $transfer->currencyCode !== strtoupper($request->currencyCode)
            || $transfer->payee !== $request->payee
            || $transfer->payeeName !== $request->payeeName
        ) {
            throw PaymentException::duplicateReference($request->externalTransferNo);
        }
    }
}
