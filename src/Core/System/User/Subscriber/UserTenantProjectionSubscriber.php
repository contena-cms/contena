<?php declare(strict_types=1);

namespace Contena\Core\System\User\Subscriber;

use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserEvents;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Projects tenant-specific user properties onto the shared user identity.
 *
 * @internal
 */
class UserTenantProjectionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [UserEvents::USER_LOADED_EVENT => 'projectMembership'];
    }

    /**
     * @param EntityLoadedEvent<UserEntity> $event
     */
    public function projectMembership(EntityLoadedEvent $event): void
    {
        $tenantId = $event->getContext()->getTenantId();
        $users = [];
        foreach ($event->getEntities() as $entity) {
            if ($entity instanceof UserEntity) {
                $users[$entity->getId()] = $entity;
            }
        }
        if ($tenantId === null || $users === []) {
            return;
        }

        $memberships = $this->connection->fetchAllAssociativeIndexed(
            <<<'SQL'
SELECT LOWER(HEX(user_id)) AS user_id, active, admin, user_code
FROM user_tenant
WHERE tenant_id = :tenantId AND user_id IN (:userIds)
SQL,
            [
                'tenantId' => Uuid::fromHexToBytes($tenantId),
                'userIds' => Uuid::fromHexToBytesList(array_keys($users)),
            ],
            ['userIds' => ArrayParameterType::BINARY],
        );

        foreach ($users as $user) {
            $membership = $memberships[$user->getId()] ?? null;
            if ($membership === null) {
                continue;
            }

            $user->setActive((bool) $membership['active']);
            $user->setAdmin((bool) $membership['admin']);
            $user->setUserCode($membership['user_code']);
        }
    }
}
