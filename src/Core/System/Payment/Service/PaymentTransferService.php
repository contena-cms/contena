<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferStates;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\TransferHandlerInterface;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Rule\PaymentRuleScope;
use Contena\Core\System\Payment\Struct\PaymentNotificationTarget;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\PaymentRoute;
use Contena\Core\System\Payment\Struct\TransferRequest;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\System\StateMachine\Transition;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * @internal
 */
final class PaymentTransferService
{
    /**
     * @param EntityRepository<PaymentTransferCollection> $paymentTransferRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentTransferRepository,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly AbstractPaymentRouteResolver $routeResolver,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function transfer(PaymentAppEntity $app, TransferRequest $request, Context $context): PaymentResult
    {
        $this->validateRequest($request);

        $existing = $this->findTransfer($app->getId(), $request->externalTransferNo, $context);
        if ($existing instanceof PaymentTransferEntity) {
            $this->assertSameTransfer($existing, $request);

            return $this->resultFromTransfer($existing);
        }

        $route = $this->routeResolver->resolve(new PaymentRuleScope(
            $context,
            $app,
            PaymentOperation::TRANSFER,
            preferredChannel: $request->channel,
            amount: $request->amount,
            currencyCode: strtoupper($request->currencyCode),
            data: $request->extra,
        ));
        if (!$route->gateway instanceof TransferHandlerInterface) {
            throw PaymentException::capabilityNotSupported($route->gateway->code(), PaymentOperation::TRANSFER);
        }

        $transferId = $this->createTransfer($app, $request, $route, $context);

        $transfer = $this->loadTransfer($transferId, $context);
        try {
            $result = $route->gateway->transfer($transfer, $route->config);
        } catch (\Throwable $exception) {
            $this->paymentTransferRepository->update([[
                'id' => $transfer->getId(),
                'resultMessage' => $exception->getMessage(),
            ]], $context);
            throw $exception;
        }

        $this->persistResult($transfer, $result, $context);

        return $result->withResource($transfer->transferNo, $transfer->externalTransferNo);
    }

    public function applyNotification(string $channel, string $channelConfigId, PaymentNotificationTarget $target, PaymentResult $result): void
    {
        $transfer = $this->loadTransfer($target->entityId, $target->context);
        if ($transfer->channelCode !== $channel || $transfer->channelConfigId !== $channelConfigId) {
            throw PaymentException::notificationConfigurationMismatch($transfer->transferNo);
        }

        if (!\in_array($transfer->state?->getTechnicalName(), [PaymentTransferStates::STATE_SUCCEEDED, PaymentTransferStates::STATE_FAILED], true)) {
            $this->persistResult($transfer, $result, $target->context);
        }
    }

    private function persistResult(PaymentTransferEntity $transfer, PaymentResult $result, Context $context): void
    {
        $this->connection->transactional(function () use ($transfer, $result, $context): void {
            $this->paymentTransferRepository->update([[
                'id' => $transfer->getId(),
                'channelOrderId' => $result->providerResourceId,
                'channelStatus' => $result->status,
                'successTime' => $result->status === PaymentStatus::SUCCEEDED ? $this->clock->now() : null,
                'responseData' => $result->toArray(),
                'resultCode' => $result->resultCode,
                'resultMessage' => $result->resultMessage,
            ]], $context);

            if ($result->status === PaymentStatus::SUCCEEDED) {
                $this->stateMachineRegistry->transition(new Transition(PaymentTransferDefinition::ENTITY_NAME, $transfer->getId(), 'succeed', 'stateId'), $context);
            } elseif (\in_array($result->status, [PaymentStatus::FAILED, PaymentStatus::CLOSED], true)) {
                $this->stateMachineRegistry->transition(new Transition(PaymentTransferDefinition::ENTITY_NAME, $transfer->getId(), 'fail', 'stateId'), $context);
            }
        });
    }

    private function initialStateId(Context $context): string
    {
        return $this->stateMachineRegistry->getStateMachine(PaymentTransferStates::STATE_MACHINE, $context)->getInitialStateId()
            ?? throw PaymentException::invalidRequest('Payment transfer state machine has no initial state.');
    }

    private function findTransfer(string $appId, string $externalTransferNo, Context $context): ?PaymentTransferEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $appId));
        $criteria->addFilter(new EqualsFilter('externalTransferNo', $externalTransferNo));
        $criteria->addAssociation('state');
        $criteria->setLimit(1);
        $transfer = $this->paymentTransferRepository->search($criteria, $context)->getEntities()->first();

        return $transfer instanceof PaymentTransferEntity ? $transfer : null;
    }

    private function loadTransfer(string $transferId, Context $context): PaymentTransferEntity
    {
        $criteria = new Criteria([$transferId]);
        $criteria->addAssociation('state');
        $transfer = $this->paymentTransferRepository->search($criteria, $context)->getEntities()->first();

        return $transfer instanceof PaymentTransferEntity ? $transfer : throw PaymentException::invalidRequest('Payment transfer could not be loaded.');
    }

    private function resultFromTransfer(PaymentTransferEntity $transfer): PaymentResult
    {
        $status = match ($transfer->state?->getTechnicalName()) {
            PaymentTransferStates::STATE_SUCCEEDED => PaymentStatus::SUCCEEDED,
            PaymentTransferStates::STATE_FAILED => PaymentStatus::FAILED,
            default => PaymentStatus::PROCESSING,
        };

        return PaymentResult::fromArray($transfer->responseData, $status)
            ->withResource($transfer->transferNo, $transfer->externalTransferNo);
    }

    private function validateRequest(TransferRequest $request): void
    {
        if ($request->externalTransferNo === '' || $request->amount <= 0 || $request->payee === '' || $request->payeeName === '') {
            throw PaymentException::invalidRequest('Transfer number, positive amount, payee and payee name are required.');
        }
        if (\strlen($request->currencyCode) !== 3) {
            throw PaymentException::invalidRequest('Transfer currency code must contain three characters.');
        }
    }

    private function assertSameTransfer(PaymentTransferEntity $transfer, TransferRequest $request): void
    {
        if ($transfer->amount !== $request->amount || $transfer->currencyCode !== strtoupper($request->currencyCode) || $transfer->payee !== $request->payee) {
            throw PaymentException::duplicateReference($request->externalTransferNo);
        }
    }

    private function createTransfer(PaymentAppEntity $app, TransferRequest $request, PaymentRoute $route, Context $context): string
    {
        $transferId = Uuid::randomHex();

        $this->paymentTransferRepository->create([[
            'id' => $transferId,
            'paymentAppId' => $app->getId(),
            'transferNo' => $this->numberRangeValueGenerator->getValue(PaymentTransferDefinition::ENTITY_NAME, $context),
            'externalTransferNo' => $request->externalTransferNo,
            'amount' => $request->amount,
            'currencyCode' => strtoupper($request->currencyCode),
            'channelCode' => $route->gateway->code(),
            'channelConfigId' => $route->channelConfigId,
            'stateId' => $this->initialStateId($context),
            'payee' => $request->payee,
            'payeeName' => $request->payeeName,
            'remark' => $request->remark,
            'notifyUrl' => $request->notifyUrl,
            'channelExtra' => $request->extra,
        ]], $context);

        return $transferId;
    }
}
