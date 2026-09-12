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
 * Projects scope-specific grant properties onto the shared user identity.
 *
 * Cross-scope reads intentionally leave these properties unset because no
 * single grant can represent a user across multiple scopes.
 *
 * @internal
 */
class UserDataScopeProjectionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [UserEvents::USER_LOADED_EVENT => 'projectGrant'];
    }

    /**
     * @param EntityLoadedEvent<UserEntity> $event
     */
    public function projectGrant(EntityLoadedEvent $event): void
    {
        if ($event->getContext()->allowsCrossScopeReads()) {
            return;
        }

        $users = [];
        foreach ($event->getEntities() as $entity) {
            if ($entity instanceof UserEntity) {
                $users[$entity->getId()] = $entity;
            }
        }
        if ($users === []) {
            return;
        }

        $grants = $this->connection->fetchAllAssociativeIndexed(
            <<<'SQL'
SELECT LOWER(HEX(user_id)) AS user_id, active, admin, read_all_scopes, user_code
FROM user_data_scope
WHERE data_scope_id = :dataScopeId AND user_id IN (:userIds)
SQL,
            [
                'dataScopeId' => Uuid::fromHexToBytes($event->getContext()->getDataScopeId()),
                'userIds' => Uuid::fromHexToBytesList(array_keys($users)),
            ],
            ['userIds' => ArrayParameterType::BINARY],
        );

        foreach ($users as $user) {
            $grant = $grants[$user->getId()] ?? null;
            if ($grant === null) {
                continue;
            }

            $user->setActive((bool) $grant['active']);
            $user->setAdmin((bool) $grant['admin']);
            $user->setReadAllScopes((bool) $grant['read_all_scopes']);
            $userCode = $grant['user_code'];
            $user->setUserCode(\is_string($userCode) ? $userCode : null);
        }
    }
}
