<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth;

use Contena\Core\Defaults;
use Contena\Core\Framework\Api\OAuth\User\User;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Tenant\Resolver\TenantResolution;
use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Bcrypt hash for a static dummy password used to equalize timing when no user is found.
     */
    private const string DUMMY_PASSWORD_HASH = '$2y$12$PVcA5R6ri9kS.7FnFUBRIOLwqU//bCicx5RFxwecAAccbmZ7V7PKu';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getUserEntityByUserCredentials(
        string $username,
        #[\SensitiveParameter]
        string $password,
        string $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        $resolution = $this->requestStack->getCurrentRequest()?->attributes->get(PlatformRequest::ATTRIBUTE_RESOLVED_TENANT_ID);

        $builder = $this->connection->createQueryBuilder();
        $user = $builder->select('user.id', 'user.password', 'user.active')
            ->from('user', 'user')
            ->where('username = :username')
            ->setParameter('username', $username);

        if ($resolution instanceof TenantResolution) {
            $builder->addSelect('COALESCE(membership.active, user.active) AS active')
                ->leftJoin(
                    'user',
                    'user_tenant',
                    'membership',
                    'membership.user_id = user.id AND membership.tenant_id = :tenantId AND membership.active = 1',
                )
                ->andWhere(<<<'SQL'
membership.user_id IS NOT NULL
OR NOT EXISTS (SELECT 1 FROM user_tenant any_membership WHERE any_membership.user_id = user.id)
SQL)
                ->setParameter('tenantId', Uuid::fromHexToBytes($resolution->tenantId))
                ->addOrderBy('membership.user_id IS NULL', 'ASC');
        } else {
            $builder->andWhere('NOT EXISTS (SELECT 1 FROM user_tenant membership WHERE membership.user_id = user.id)');
        }

        $user = $builder->fetchAssociative();

        if (!$user) {
            // Prevent user enumeration via timing attacks by always running password_verify().
            $user = ['password' => self::DUMMY_PASSWORD_HASH];
            $password = 'invalid-password-will-always-fail';
        }

        if (!password_verify($password, (string) $user['password'])) {
            return null;
        }

        if (!(bool) $user['active']) {
            return null;
        }

        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->connection->executeStatement(
            'UPDATE `user` SET `first_login` = COALESCE(`first_login`, :now), `last_login` = :now WHERE `id` = :id',
            ['now' => $now, 'id' => $user['id']]
        );

        return new User(Uuid::fromBytesToHex($user['id']));
    }
}
