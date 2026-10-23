<?php declare(strict_types=1);

namespace Contena\Core\System\User\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Checkout\Order\OrderStates;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\User\Subscriber\UserMetaFieldSubscriberTest
 */
class UserMetaFieldSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'fillUserMetaDataFields',
            PreWriteValidationEvent::class => 'deleteOrder',
        ];
    }

    public function fillUserMetaDataFields(StateMachineTransitionEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        if ($event->getEntityName() !== 'order') {
            return;
        }

        if ($event->getToPlace()->getTechnicalName() !== OrderStates::STATE_COMPLETED && $event->getFromPlace()->getTechnicalName() !== OrderStates::STATE_COMPLETED) {
            return;
        }

        $this->updateUser([$event->getEntityId()]);
    }

    public function deleteOrder(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $orderIds = [];
        foreach ($event->getDeletedPrimaryKeys(OrderDefinition::ENTITY_NAME) as $primaryKey) {
            $orderIds[] = Uuid::fromBytesToHex($primaryKey['id']);
        }

        $this->updateUser($orderIds, true);
    }

    /**
     * @param array<string> $orderIds
     */
    private function updateUser(array $orderIds, bool $isDelete = false): void
    {
        if ($orderIds === []) {
            return;
        }

        $userIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(user_id)) FROM `order_customer` WHERE order_id IN (:ids) AND order_version_id = :version AND user_id IS NOT NULL',
            ['ids' => Uuid::fromHexToBytesList($orderIds), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        if ($userIds === []) {
            return;
        }

        $parameters = [
            'userIds' => Uuid::fromHexToBytesList($userIds),
            'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'state' => OrderStates::STATE_COMPLETED,
        ];
        $types = [
            'userIds' => ArrayParameterType::BINARY,
        ];

        $whereOrder = '';
        if ($isDelete) {
            $whereOrder = 'AND `order`.id NOT IN (:exceptOrderIds)';
            $parameters['exceptOrderIds'] = Uuid::fromHexToBytesList($orderIds);
            $types['exceptOrderIds'] = ArrayParameterType::BINARY;
        }

        $select = '
            SELECT LOWER(HEX(`order_customer`.user_id)) as user_id,
                   `order_customer`.user_id as id,
                   COUNT(`order`.id) as order_count,
                   ROUND(SUM(`order`.amount_total / `order`.currency_factor), 2) as order_total_amount,
                   MAX(`order`.order_date_time) as last_order_date

            FROM `order_customer`

            INNER JOIN `order`
                ON `order`.id = `order_customer`.order_id
                AND `order`.version_id = `order_customer`.order_version_id
                AND `order`.version_id = :version
                ' . $whereOrder . '

            INNER JOIN `state_machine_state`
                ON `state_machine_state`.id = `order`.state_id
                AND `state_machine_state`.technical_name = :state

            WHERE `order_customer`.user_id IN (:userIds)
            GROUP BY `order_customer`.user_id
        ';

        $data = [];
        foreach ($userIds as $userId) {
            $data[$userId] = [
                'id' => Uuid::fromHexToBytes($userId),
                'order_count' => 0,
                'order_total_amount' => 0,
                'last_order_date' => null,
            ];
        }

        // indexed by the hex user id (first column), matching the keys of the reset values above
        $aggregated = $this->connection->fetchAllAssociativeIndexed($select, $parameters, $types);

        $data = array_replace($data, $aggregated);

        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `user` SET order_count = :order_count, order_total_amount = :order_total_amount, last_order_date = :last_order_date WHERE id = :id')
        );

        foreach ($data as $record) {
            $update->execute($record);
        }
    }
}
