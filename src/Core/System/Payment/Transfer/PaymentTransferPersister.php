<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Transfer;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferStates;
use Contena\Core\System\Payment\Event\PaymentEntityCreatedEvent;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Persists new transfer orders.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
class PaymentTransferPersister
{
    /**
     * @param EntityRepository<PaymentTransferCollection> $paymentTransferRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentTransferRepository,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param array<string, mixed> $transferData
     */
    public function persist(array $transferData, Context $context): string
    {
        $transferId = Uuid::randomHex();

        $this->connection->transactional(function () use ($transferData, $context, $transferId): void {
            $this->paymentTransferRepository->create([[
                ...$transferData,
                'id' => $transferId,
                'transferNo' => $this->numberRangeValueGenerator->getValue(PaymentTransferDefinition::ENTITY_NAME, $context),
                'stateId' => $this->stateMachineRegistry->getStateMachine(PaymentTransferStates::STATE_MACHINE, $context)->getInitialStateId()
                    ?? throw PaymentException::invalidRequest('Payment transfer state machine has no initial state.'),
            ]], $context);
            $this->eventDispatcher->dispatch(new PaymentEntityCreatedEvent(
                new PaymentEntityReference(PaymentTransferDefinition::ENTITY_NAME, $transferId),
                $context,
            ));
        });

        return $transferId;
    }
}
