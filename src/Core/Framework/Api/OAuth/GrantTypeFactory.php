<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth;

use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Sso\ContenaGrantType;
use Contena\Core\Framework\Sso\ContenaPasswordGrantType;
use Contena\Core\Framework\Sso\ContenaRefreshTokenGrantType;
use Contena\Core\Framework\Sso\TokenService\ExternalTokenService;
use Contena\Core\Framework\Sso\UserService\UserService;

/**
 * Builds the grant types that are enabled on the Admin API authorization server for each request.
 *
 * @internal
 */
#[Package('framework')]
final class GrantTypeFactory
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly AuthCodeRepositoryInterface $authCodeRepository,
        private readonly UserService $userService,
        private readonly ExternalTokenService $tokenService,
        private readonly ClockInterface $clock,
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

        $passwordGrant = new ContenaPasswordGrantType($this->userRepository, $this->refreshTokenRepository, $this->userService);
        $passwordGrant->setRefreshTokenTTL($refreshTokenInterval);

        $refreshTokenGrant = new ContenaRefreshTokenGrantType(
            $this->refreshTokenRepository,
            $this->userService,
            $this->tokenService,
            $this->clock
        );
        $refreshTokenGrant->setRefreshTokenTTL($refreshTokenInterval);

        $contenaGrant = new ContenaGrantType($this->refreshTokenRepository, $this->userService, $this->tokenService, $this->clock);
        $contenaGrant->setRefreshTokenTTL($refreshTokenInterval);

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
            $contenaGrant,
            $authCodeGrant,
        ];
    }
}
