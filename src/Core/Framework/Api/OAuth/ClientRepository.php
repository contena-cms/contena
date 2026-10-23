<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Psr\Clock\ClockInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\OAuth\Client\ApiClient;
use Contena\Core\Framework\Api\OAuth\Client\PublicClientRegistry;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * OAuth integrations should rely on {@see ClientRepositoryInterface} instead of this concrete Contena class.
 */
#[BecomesInternal(version: 'v6.8.0')]
class ClientRepository implements ClientRepositoryInterface
{
    /**
     * Bcrypt hash for a static placeholder secret used to equalize timing when no client is found.
     */
    private const PLACEHOLDER_CLIENT_SECRET_HASH = '$2y$12$PVcA5R6ri9kS.7FnFUBRIOLwqU//bCicx5RFxwecAAccbmZ7V7PKu';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly PublicClientRegistry $publicClients,
    ) {
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        if (($grantType === 'password' || $grantType === 'refresh_token') && $clientIdentifier === 'administration') {
            return true;
        }

        if (\in_array($grantType, PublicClientRegistry::GRANT_TYPES, true) && $this->publicClients->has($clientIdentifier)) {
            return true;
        }

        if ($grantType === 'client_credentials' && $clientSecret !== null) {
            $values = $this->getByAccessKey($clientIdentifier);

            if (!$values) {
                // Prevent client enumeration via timing attacks by always running password_verify().
                $values = ['secret_access_key' => self::PLACEHOLDER_CLIENT_SECRET_HASH];
                $clientSecret = 'invalid-secret-will-always-fail';
            }

            if (!password_verify($clientSecret, (string) $values['secret_access_key'])) {
                return false;
            }

            $id = $values['id'] ?? '';
            if ($id !== '') {
                $this->updateLastUsageDate($id);
            }

            return true;
        }

        // @codeCoverageIgnoreStart
        throw OAuthServerException::unsupportedGrantType();
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param non-empty-string $clientIdentifier
     */
    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        if ($clientIdentifier === 'administration') {
            return new ApiClient('administration', true, confidential: false);
        }

        $publicClient = $this->publicClients->get($clientIdentifier);
        if ($publicClient !== null) {
            return $publicClient;
        }

        $accessKey = $this->getByAccessKey($clientIdentifier);

        if ($accessKey === null) {
            // Prevent client enumeration via timing attacks by always running password_verify().
            password_verify('invalid-secret-will-always-fail', self::PLACEHOLDER_CLIENT_SECRET_HASH);

            return null;
        }

        $admin_userId = $accessKey['admin_user_id'] ?? null;

        return new ApiClient(
            $clientIdentifier,
            true,
            name: $admin_userId !== null ? Uuid::fromBytesToHex($admin_userId) : $accessKey['label'] ?? '',
            confidential: true
        );
    }

    public function updateLastUsageDate(string $integrationId): void
    {
        $this->connection->update(
            'integration',
            ['last_usage_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
            ['id' => $integrationId]
        );
    }

    /**
     * @return array{admin_user_id: string, secret_access_key: string}|array{id: string, label: string, secret_access_key: string}|null
     */
    private function getByAccessKey(string $clientIdentifier): ?array
    {
        $origin = AccessKeyHelper::getOrigin($clientIdentifier);

        if ($origin === 'admin_user') {
            return $this->getUserByAccessKey($clientIdentifier);
        }

        if ($origin === 'integration') {
            return $this->getIntegrationByAccessKey($clientIdentifier);
        }

        return null;
    }

    /**
     * @return array{admin_user_id: string, secret_access_key: string}|null
     */
    private function getUserByAccessKey(string $clientIdentifier): ?array
    {
        /** @var array{admin_user_id: string, secret_access_key: string}|false $key */
        $key = $this->connection->fetchAssociative(
            'SELECT admin_user_id, secret_access_key
             FROM admin_user_access_key
             WHERE access_key = :accessKey',
            ['accessKey' => $clientIdentifier]
        );

        if ($key === false) {
            return null;
        }

        return $key;
    }

    /**
     * @return array{id: string, label: string, secret_access_key: string}|null
     */
    private function getIntegrationByAccessKey(string $clientIdentifier): ?array
    {
        /** @var array{id: string, label: string, active: '1'|'0', secret_access_key: string}|false $key */
        $key = $this->connection->fetchAssociative(
            'SELECT integration.id AS id, label, app.active AS active, secret_access_key
             FROM integration
             LEFT JOIN app ON app.integration_id = integration.id
             WHERE access_key = :accessKey',
            ['accessKey' => $clientIdentifier]
        );

        if ($key === false) {
            return null;
        }

        // inactive apps cannot access the api
        // if the integration is not associated to an app `active` will be null
        if ($key['active'] === '0') {
            return null;
        }
        unset($key['active']);

        return $key;
    }
}
