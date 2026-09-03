<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentNotificationTypes;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentNotificationTarget;

/**
 * @internal
 */
final class PaymentNotificationTargetResolver
{
    /**
     * @param EntityRepository<PaymentOrderCollection> $orderRepository
     * @param EntityRepository<PaymentRefundCollection> $refundRepository
     * @param EntityRepository<PaymentTransferCollection> $transferRepository
     * @param EntityRepository<PaymentRecurringCollection> $recurringRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $refundRepository,
        private readonly EntityRepository $transferRepository,
        private readonly EntityRepository $recurringRepository,
    ) {
    }

    public function resolve(int $type, string $resourceNo): PaymentNotificationTarget
    {
        return match ($type) {
            PaymentNotificationTypes::PAYMENT => $this->resolveOrder($resourceNo),
            PaymentNotificationTypes::REFUND => $this->resolveRefund($resourceNo),
            PaymentNotificationTypes::TRANSFER => $this->resolveTransfer($resourceNo),
            PaymentNotificationTypes::SUBSCRIPTION => $this->resolveRecurring($resourceNo),
            default => throw PaymentException::invalidRequest('The payment notification type is not supported.'),
        };
    }

    private function resolveOrder(string $orderNo): PaymentNotificationTarget
    {
        $criteria = $this->criteria('orderNo', $orderNo);
        $entity = $this->orderRepository->search($criteria, Context::createGlobalContext())->getEntities()->first();
        if (!$entity instanceof PaymentOrderEntity) {
            throw PaymentException::notificationResourceNotFound($orderNo);
        }

        return new PaymentNotificationTarget(PaymentOrderDefinition::ENTITY_NAME, $entity->getId(), $entity->externalOrderNo, $entity->notifyUrl, $this->context($entity->tenantId));
    }

    private function resolveRefund(string $refundNo): PaymentNotificationTarget
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('refundNo', $refundNo));
        $criteria->addAssociation('order');
        $criteria->setLimit(1);
        $entity = $this->refundRepository->search($criteria, Context::createGlobalContext())->getEntities()->first();
        if (!$entity instanceof PaymentRefundEntity || !$entity->order instanceof PaymentOrderEntity) {
            throw PaymentException::notificationResourceNotFound($refundNo);
        }

        return new PaymentNotificationTarget(PaymentRefundDefinition::ENTITY_NAME, $entity->getId(), $entity->externalRefundNo, $entity->order->notifyUrl, $this->context($entity->tenantId));
    }

    private function resolveTransfer(string $transferNo): PaymentNotificationTarget
    {
        $criteria = $this->criteria('transferNo', $transferNo);
        $entity = $this->transferRepository->search($criteria, Context::createGlobalContext())->getEntities()->first();
        if (!$entity instanceof PaymentTransferEntity) {
            throw PaymentException::notificationResourceNotFound($transferNo);
        }

        return new PaymentNotificationTarget(PaymentTransferDefinition::ENTITY_NAME, $entity->getId(), $entity->externalTransferNo, $entity->notifyUrl, $this->context($entity->tenantId));
    }

    private function resolveRecurring(string $recurringNo): PaymentNotificationTarget
    {
        $criteria = $this->criteria('recurringNo', $recurringNo);
        $entity = $this->recurringRepository->search($criteria, Context::createGlobalContext())->getEntities()->first();
        if (!$entity instanceof PaymentRecurringEntity) {
            throw PaymentException::notificationResourceNotFound($recurringNo);
        }

        return new PaymentNotificationTarget(PaymentRecurringDefinition::ENTITY_NAME, $entity->getId(), $entity->externalRecurringNo, $entity->notifyUrl, $this->context($entity->tenantId));
    }

    private function criteria(string $field, string $value): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($field, $value));
        $criteria->setLimit(1);

        return $criteria;
    }

    private function context(?string $tenantId): Context
    {
        return $tenantId === null ? Context::createDefaultContext() : Context::createTenantContext($tenantId);
    }
}
