<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentTransactionStates;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderStates;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Struct\PaymentRoute;
use Contena\Core\System\StateMachine\StateMachineRegistry;

/**
 * Converts payment order inputs into DAL write payloads.
 *
 * @internal
 */
final class PaymentOrderConverter
{
    public function __construct(
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
        private readonly StateMachineRegistry $stateMachineRegistry,
    ) {
    }

    /**
     * @return array{orderId: string, transactionId: string, order: array<string, mixed>}
     */
    public function convert(PaymentAppEntity $app, PaymentRequest $request, PaymentRoute $route, Context $context): array
    {
        $orderId = Uuid::randomHex();
        $transactionId = Uuid::randomHex();
        $orderNo = $this->numberRangeValueGenerator->getValue(PaymentOrderDefinition::ENTITY_NAME, $context);
        $transactionNo = $this->numberRangeValueGenerator->getValue(PaymentOrderTransactionDefinition::ENTITY_NAME, $context);
        $currencyCode = strtoupper($request->currencyCode);
        $channelCode = $route->gateway->code();

        return [
            'orderId' => $orderId,
            'transactionId' => $transactionId,
            'order' => [
                'id' => $orderId,
                'paymentAppId' => $app->getId(),
                'orderNo' => $orderNo,
                'externalOrderNo' => $request->externalOrderNo,
                'amount' => $request->amount,
                'currencyCode' => $currencyCode,
                'channelCode' => $channelCode,
                'methodCode' => $request->method,
                'deviceType' => $request->deviceType ?? $request->method,
                'subject' => $request->subject,
                'clientIp' => $request->clientIp,
                'channelExtra' => $request->extra,
                'notifyUrl' => $request->notifyUrl,
                'returnUrl' => $request->returnUrl,
                'channelConfigId' => $route->channelConfigId,
                'stateId' => $this->initialStateId(PaymentOrderStates::STATE_MACHINE, $context),
                'transactions' => [$this->initialTransaction(
                    $transactionId,
                    $transactionNo,
                    $channelCode,
                    $request,
                    $context,
                )],
            ],
        ];
    }

    /**
     * @return array{transactionId: string, transaction: array<string, mixed>}
     */
    public function convertQueryTransaction(PaymentOrderEntity $order, Context $context): array
    {
        $transactionId = Uuid::randomHex();

        return [
            'transactionId' => $transactionId,
            'transaction' => [
                'id' => $transactionId,
                'orderId' => $order->getId(),
                'transactionNo' => $this->numberRangeValueGenerator->getValue(PaymentOrderTransactionDefinition::ENTITY_NAME, $context),
                'type' => 'query',
                'channelCode' => $order->channelCode,
                'methodCode' => $order->methodCode,
                'amount' => 0,
                'stateId' => $this->initialStateId(PaymentTransactionStates::STATE_MACHINE, $context),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function initialTransaction(
        string $transactionId,
        string $transactionNo,
        string $channelCode,
        PaymentRequest $request,
        Context $context,
    ): array {
        return [
            'id' => $transactionId,
            'transactionNo' => $transactionNo,
            'type' => 'create',
            'channelCode' => $channelCode,
            'methodCode' => $request->method,
            'amount' => $request->amount,
            'stateId' => $this->initialStateId(PaymentTransactionStates::STATE_MACHINE, $context),
        ];
    }

    private function initialStateId(string $stateMachine, Context $context): string
    {
        return $this->stateMachineRegistry->getStateMachine($stateMachine, $context)->getInitialStateId()
            ?? throw PaymentException::invalidRequest('Payment state machine has no initial state.');
    }
}
