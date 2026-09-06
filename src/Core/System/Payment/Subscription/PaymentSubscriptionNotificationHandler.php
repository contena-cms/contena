<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Subscription;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentNotificationTypes;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringDefinition;
use Contena\Core\System\Payment\Notification\PaymentNotificationHandlerInterface;
use Contena\Core\System\Payment\Notification\Struct\PaymentNotificationTarget;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
final class PaymentSubscriptionNotificationHandler implements PaymentNotificationHandlerInterface
{
    /**
     * @param EntityRepository<PaymentRecurringCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly PaymentSubscriptionPersister $persister,
    ) {
    }

    public function getType(): int
    {
        return PaymentNotificationTypes::SUBSCRIPTION;
    }

    public function resolve(string $resourceNo, string $channelConfigId): PaymentNotificationTarget
    {
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('recurringNo', $resourceNo))
            ->addFilter(new EqualsFilter('channelConfigId', $channelConfigId))
            ->setLimit(2);
        $entities = $this->repository->search($criteria, Context::createGlobalContext())->getEntities();
        $entity = $entities->first();
        if ($entities->count() !== 1 || $entity === null) {
            throw PaymentException::notificationResourceNotFound($resourceNo);
        }

        $context = $entity->tenantId === null ? Context::createDefaultContext() : Context::createTenantContext($entity->tenantId);

        return new PaymentNotificationTarget(PaymentRecurringDefinition::ENTITY_NAME, $entity->getId(), $context, 'recurringId');
    }

    public function apply(string $channel, string $channelConfigId, PaymentNotificationTarget $target, PaymentResult $result): void
    {
        $this->persister->applyNotification($channel, $channelConfigId, $target, $result);
    }
}
