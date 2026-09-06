<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentTransactionStates;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderStates;
use Contena\Core\System\Payment\Event\PaymentEntityCreatedEvent;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Persists new payment orders and their provider-facing transactions.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
class PaymentOrderPersister
{
    /**
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     * @param EntityRepository<PaymentOrderTransactionCollection> $paymentOrderTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentOrderRepository,
        private readonly EntityRepository $paymentOrderTransactionRepository,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
        private readonly InitialStateIdLoader $initialStateIdLoader,
    ) {
    }

    /**
     * @param array<string, mixed> $orderData
     */
    public function persist(array $orderData, Context $context): string
    {
        $orderId = Uuid::randomHex();
        $order = [
            ...$orderData,
            'id' => $orderId,
            'orderNo' => $this->numberRangeValueGenerator->getValue(PaymentOrderDefinition::ENTITY_NAME, $context),
            'stateId' => $this->initialStateIdLoader->get(PaymentOrderStates::STATE_MACHINE),
        ];

        $this->connection->transactional(function () use ($order, $orderId, $context): void {
            $this->paymentOrderRepository->create([$order], $context);
            $this->eventDispatcher->dispatch(new PaymentEntityCreatedEvent(
                new PaymentEntityReference(PaymentOrderDefinition::ENTITY_NAME, $orderId),
                $context,
            ));
        });

        return $orderId;
    }

    public function persistPrimaryTransaction(PaymentOrderEntity $order, Context $context): string
    {
        return $this->connection->transactional(function () use ($order, $context): string {
            $transactionId = Uuid::randomHex();
            $this->paymentOrderTransactionRepository->create([[
                'id' => $transactionId,
                'orderId' => $order->getId(),
                'transactionNo' => $this->numberRangeValueGenerator->getValue(PaymentOrderTransactionDefinition::ENTITY_NAME, $context),
                'channelCode' => $order->channelCode,
                'methodCode' => $order->methodCode,
                'amount' => $order->amount,
                'stateId' => $this->initialStateIdLoader->get(PaymentTransactionStates::STATE_MACHINE),
            ]], $context);
            $this->paymentOrderRepository->update([['id' => $order->getId(), 'primaryTransactionId' => $transactionId]], $context);

            return $transactionId;
        });
    }
}
