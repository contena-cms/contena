<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Notification;

use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordStatus;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\Event\PaymentStatusChangedEvent;
use Contena\Core\System\Payment\Notification\PaymentNotificationTypes;
use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Owns OpenApi payloads and delivery destinations. Domain events only carry identity.
 * The envelope is stored in the result transaction; delivery is asynchronous.
 *
 * @internal
 */
final class AppNotificationSubscriber implements EventSubscriberInterface
{
    private const array ENVELOPES = [
        'payment_order' => ['notifyType' => PaymentNotificationTypes::PAYMENT, 'type' => 'payment', 'association' => 'orderId', 'number' => 'orderNo', 'externalNumber' => 'externalOrderNo'],
        'payment_refund' => ['notifyType' => PaymentNotificationTypes::REFUND, 'type' => 'refund', 'association' => 'refundId', 'number' => 'refundNo', 'externalNumber' => 'externalRefundNo'],
        'payment_transfer' => ['notifyType' => PaymentNotificationTypes::TRANSFER, 'type' => 'transfer', 'association' => 'transferId', 'number' => 'transferNo', 'externalNumber' => 'externalTransferNo'],
        'payment_recurring' => ['notifyType' => PaymentNotificationTypes::SUBSCRIPTION, 'type' => 'subscription', 'association' => 'recurringId', 'number' => 'recurringNo', 'externalNumber' => 'externalRecurringNo'],
    ];

    /**
     * @param EntityRepository<PaymentNotifyRecordCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [PaymentStatusChangedEvent::class => 'enqueue'];
    }

    public function enqueue(PaymentStatusChangedEvent $event): void
    {
        $reference = $event->entity;
        $envelope = self::ENVELOPES[$reference->entityName] ?? null;
        if ($envelope === null) {
            return;
        }

        $criteria = new Criteria([$reference->entityId]);
        if ($reference->entityName === 'payment_refund') {
            $criteria->addAssociation('order');
        }
        $entity = $this->definitionRegistry->getRepository($reference->entityName)->search($criteria, $event->getContext())->getEntities()->first();

        if ($entity === null) {
            throw PaymentException::notificationResourceNotFound($reference->entityId);
        }

        $notifyUrl = $entity instanceof PaymentRefundEntity ? $entity->order?->notifyUrl : $entity->get('notifyUrl');
        if ($notifyUrl === null || $notifyUrl === '') {
            return;
        }

        $this->repository->create([[
            'id' => Uuid::randomHex(),
            'notifyType' => $envelope['notifyType'],
            'notifyUrl' => $notifyUrl,
            'requestBody' => json_encode([
                'type' => $envelope['type'],
                'resource_no' => $entity->get($envelope['number']),
                'external_resource_no' => $entity->get($envelope['externalNumber']),
                'status' => $event->gatewayResult->status,
                'result_code' => $event->gatewayResult->response->code,
                'result_message' => $event->gatewayResult->response->message,
            ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            'status' => PaymentNotifyRecordStatus::STATUS_PENDING,
            'retryCount' => 0,
            $envelope['association'] => $reference->entityId,
        ]], $event->getContext());
    }
}
