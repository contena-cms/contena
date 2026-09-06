<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Subscription;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringStatus;
use Contena\Core\System\Payment\Event\PaymentEntityCreatedEvent;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Persists new subscription agreements.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
class PaymentSubscriptionPersister
{
    /**
     * @param EntityRepository<PaymentRecurringCollection> $paymentRecurringRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentRecurringRepository,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param array<string, mixed> $subscriptionData
     */
    public function persist(array $subscriptionData, Context $context): string
    {
        $subscriptionId = Uuid::randomHex();

        $this->connection->transactional(function () use ($subscriptionData, $context, $subscriptionId): void {
            $this->paymentRecurringRepository->create([[
                ...$subscriptionData,
                'id' => $subscriptionId,
                'recurringNo' => $this->numberRangeValueGenerator->getValue(PaymentRecurringDefinition::ENTITY_NAME, $context),
                'status' => PaymentRecurringStatus::STATUS_PENDING,
            ]], $context);
            $this->eventDispatcher->dispatch(new PaymentEntityCreatedEvent(
                new PaymentEntityReference(PaymentRecurringDefinition::ENTITY_NAME, $subscriptionId),
                $context,
            ));
        });

        return $subscriptionId;
    }
}
