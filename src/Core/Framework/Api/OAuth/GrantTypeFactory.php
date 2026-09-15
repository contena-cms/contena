<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth;

use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;

/**
 * Builds the grant types that are enabled on the Admin API authorization server for each request.
 *
 * @internal
 */
final class GrantTypeFactory
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly AuthCodeRepositoryInterface $authCodeRepository,
        private readonly string $refreshTokenTtl = 'P1W',
        private readonly string $authCodeTtl = 'PT5M',
    ) {
    }

    /**
     * @return list<GrantTypeInterface>
     */
    public function createGrantTypes(): array
    {
        $refreshTokenInterval = new \DateInterval($this->refreshTokenTtl);

        $passwordGrant = new PasswordGrant($this->userRepository, $this->refreshTokenRepository);
        $passwordGrant->setRefreshTokenTTL($refreshTokenInterval);

        $refreshTokenGrant = new RefreshTokenGrant($this->refreshTokenRepository);
        $refreshTokenGrant->setRefreshTokenTTL($refreshTokenInterval);

        $authCodeGrant = new ContenaAuthCodeGrantType(
            $this->authCodeRepository,
            $this->refreshTokenRepository,
            new \DateInterval($this->authCodeTtl)
        );
        $authCodeGrant->setRefreshTokenTTL($refreshTokenInterval);

        return [
            $passwordGrant,
            $refreshTokenGrant,
            new ClientCredentialsGrant(),
            $authCodeGrant,
        ];
    }
}
