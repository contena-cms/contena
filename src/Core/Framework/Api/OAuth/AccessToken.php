<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Log\Package;

/**
 * OAuth integrations should rely on {@see AccessTokenEntityInterface} instead of this concrete Contena class.
 */
#[BecomesInternal(version: 'v6.8.0')]
class AccessToken implements AccessTokenEntityInterface
{
    use AccessTokenTrait;
    use EntityTrait;
    use RefreshTokenTrait;

    /**
     * @internal
     *
     * @param ScopeEntityInterface[] $scopes
     * @param non-empty-string|null $admin_userIdentifier
     */
    public function __construct(
        private ClientEntityInterface $client,
        private array $scopes,
        private ?string $admin_userIdentifier = null
    ) {
    }

    public function getClient(): ClientEntityInterface
    {
        return $this->client;
    }

    /**
     * @return non-empty-string|null
     */
    public function getUserIdentifier(): ?string
    {
        return $this->admin_userIdentifier;
    }

    /**
     * @return ScopeEntityInterface[]
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * Set the identifier of the admin_user associated with the token.
     *
     * @param non-empty-string $identifier The identifier of the admin_user
     */
    public function setUserIdentifier(string $identifier): void
    {
        $this->admin_userIdentifier = $identifier;
    }

    /**
     * Set the client that the token was issued to.
     */
    public function setClient(ClientEntityInterface $client): void
    {
        $this->client = $client;
    }

    /**
     * Associate a scope with the token.
     */
    public function addScope(ScopeEntityInterface $scope): void
    {
        $this->scopes[] = $scope;
    }

    public function initJwtConfiguration(): void
    {
        if (!$this->privateKey instanceof FakeCryptKey) {
            $jwtConfig = JWTConfigurationFactory::createJWTConfiguration();
            $this->privateKey = new FakeCryptKey($jwtConfig);
        }

        $this->jwtConfiguration = $this->privateKey->configuration;
    }
}
