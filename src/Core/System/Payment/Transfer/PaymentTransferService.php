<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Transfer;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferStates;
use Contena\Core\System\Payment\Gateway\GatewayOperationExecutor;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\TransferHandlerInterface;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Routing\PaymentRoutingRequest;
use Contena\Core\System\Payment\Struct\GatewayResult;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
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
    /**
     * @param EntityRepository<PaymentTransferCollection> $paymentTransferRepository
     */
    public function __construct(
        private readonly PaymentTransferPersister $persister,
        private readonly PaymentTransferStateHandler $stateHandler,
        private readonly EntityRepository $paymentTransferRepository,
        private readonly AbstractPaymentRouteResolver $routeResolver,
        private readonly GatewayOperationExecutor $gatewayExecutor,
    ) {
    }

    public function getDecorated(): AbstractPaymentTransferService
    {
        throw new DecorationPatternException(self::class);
    }

    public function transfer(PaymentAppEntity $app, TransferRequest $request, Context $context): PaymentResult
    {
        if (!$app->status || $app->tenantId !== $context->getTenantId()) {
            throw PaymentException::appNotFound($app->appCode);
        }
        if ($context->hasGlobalTenantAccess()) {
            throw PaymentException::invalidRequest('Payment operations require a platform or tenant context.');
        }

        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('paymentAppId', $app->getId()))
            ->addFilter(new EqualsFilter('externalTransferNo', $request->externalTransferNo))
            ->addAssociation('state')
            ->setLimit(1);
        $existingTransfer = $this->paymentTransferRepository->search($criteria, $context)->getEntities()->first();
        if ($existingTransfer instanceof PaymentTransferEntity) {
            if ($existingTransfer->amount !== $request->amount
                || $existingTransfer->currencyCode !== strtoupper($request->currencyCode)
                || $existingTransfer->payee !== $request->payee
                || $existingTransfer->payeeName !== $request->payeeName
            ) {
                throw PaymentException::duplicateReference($request->externalTransferNo);
            }
            $status = match ($existingTransfer->state?->getTechnicalName()) {
                PaymentTransferStates::STATE_SUCCEEDED => PaymentStatus::SUCCEEDED,
                PaymentTransferStates::STATE_FAILED => PaymentStatus::FAILED,
                default => PaymentStatus::PROCESSING,
            };

            return new PaymentResult(
                $existingTransfer->transferNo,
                $existingTransfer->externalTransferNo,
                GatewayResult::fromArray($existingTransfer->responseData, $status),
            );
        }

        $route = $this->routeResolver->resolve($app, $context, new PaymentRoutingRequest(PaymentOperation::TRANSFER, TransferHandlerInterface::class, preferredChannel: $request->channel, amount: $request->amount, currencyCode: $request->currencyCode));
        if (!$route->gateway instanceof TransferHandlerInterface) {
            throw PaymentException::capabilityNotSupported($route->gateway->code(), PaymentOperation::TRANSFER);
        }

        $transferId = $this->persister->persist([
            'paymentAppId' => $app->getId(),
            'externalTransferNo' => $request->externalTransferNo,
            'amount' => $request->amount,
            'currencyCode' => strtoupper($request->currencyCode),
            'channelCode' => $route->gateway->code(),
            'channelConfigId' => $route->channelConfigId,
            'payee' => $request->payee,
            'payeeName' => $request->payeeName,
            'remark' => $request->remark,
            'notifyUrl' => $request->notifyUrl,
            'channelExtra' => $request->extra,
        ], $context);
        $transferCriteria = new Criteria([$transferId]);
        $transferCriteria->addAssociation('state');
        $transfer = $this->paymentTransferRepository->search($transferCriteria, $context)->getEntities()->first()
            ?? throw PaymentException::transferNotFound($transferId);
        try {
            $gatewayResult = $this->gatewayExecutor->execute(
                PaymentOperation::TRANSFER,
                new PaymentEntityReference(PaymentTransferDefinition::ENTITY_NAME, $transfer->getId()),
                $route,
                $context,
                fn (): GatewayResult => $route->gateway->transfer($transfer, $route->config),
            );
        } catch (\Throwable $exception) {
            $this->stateHandler->recordFailure($transfer, $exception, $context);
            throw $exception;
        }

        $gatewayResult = $this->stateHandler->apply($transfer, $gatewayResult, $context);

        return new PaymentResult($transfer->transferNo, $transfer->externalTransferNo, $gatewayResult);
    }
}
