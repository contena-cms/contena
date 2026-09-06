<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Refund;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundDefinition;
use Contena\Core\System\Payment\Notification\PaymentNotificationHandlerInterface;
use Contena\Core\System\Payment\Notification\PaymentNotificationTypes;
use Contena\Core\System\Payment\Notification\Struct\PaymentNotificationTarget;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\GatewayResult;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
final class PaymentRefundNotificationHandler implements PaymentNotificationHandlerInterface
{
    /**
     * @param EntityRepository<PaymentRefundCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly PaymentRefundStateHandler $stateHandler,
    ) {
    }

    public function getType(): int
    {
        return PaymentNotificationTypes::REFUND;
    }

    public function resolve(string $resourceNo, string $channelConfigId): PaymentNotificationTarget
    {
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('refundNo', $resourceNo))
            ->addFilter(new EqualsFilter('order.channelConfigId', $channelConfigId))
            ->setLimit(2);
        $entities = $this->repository->search($criteria, Context::createGlobalContext())->getEntities();
        $entity = $entities->first();
        if ($entities->count() !== 1 || $entity === null) {
            throw PaymentException::notificationResourceNotFound($resourceNo);
        }

        $context = $entity->tenantId === null ? Context::createDefaultContext() : Context::createTenantContext($entity->tenantId);

        return new PaymentNotificationTarget(PaymentRefundDefinition::ENTITY_NAME, $entity->getId(), $context, 'refundId');
    }

    public function apply(string $channel, string $channelConfigId, PaymentNotificationTarget $target, GatewayResult $result): void
    {
        $criteria = new Criteria([$target->entityId]);
        $criteria->addAssociation('order');
        $refund = $this->repository->search($criteria, $target->context)->getEntities()->first()
            ?? throw PaymentException::notificationResourceNotFound($target->entityId);
        $order = $refund->order;
        if (!$order instanceof PaymentOrderEntity) {
            throw PaymentException::notificationResourceNotFound($refund->refundNo);
        }
        if ($refund->channelCode !== $channel || $order->channelConfigId !== $channelConfigId) {
            throw PaymentException::notificationConfigurationMismatch($refund->refundNo);
        }

        $this->stateHandler->apply($refund, $order, $result, $target->context);
    }
}
