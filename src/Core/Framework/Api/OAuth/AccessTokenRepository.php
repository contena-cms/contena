<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Contena\Core\Framework\Api\OAuth\Client\ApiClient;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;

/**
 * OAuth integrations should rely on {@see AccessTokenRepositoryInterface} instead of this concrete Contena class.
 */
#[BecomesInternal(version: 'v6.8.0')]
class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    /**
     * {@inheritdoc}
     *
     * @param non-empty-string|null $adminUserIdentifier
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, ?string $adminUserIdentifier = null): AccessTokenEntityInterface
    {
        $token = new AccessToken($clientEntity, $scopes, $adminUserIdentifier);

        if ($clientEntity instanceof ApiClient && $clientEntity->getIdentifier() === 'administration') {
            $token->setIdentifier('administration');
        }

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function revokeAccessToken(string $tokenId): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function isAccessTokenRevoked(string $tokenId): bool
    {
        return false;
    }
}
