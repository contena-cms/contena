<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth\Client;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;

class ApiClient implements ClientEntityInterface
{
    use ClientTrait;

    /**
     * @param non-empty-string $identifier
     */
    /**
     * @param non-empty-string $identifier
     * @param list<string> $redirectUris
     * @param list<string>|null $grantTypes
     */
    public function __construct(
        private readonly string $identifier,
        private readonly bool $writeAccess,
        string $name = '',
        private readonly bool $confidential = true,
        array $redirectUris = [],
        private readonly ?array $grantTypes = null,
    ) {
        $this->name = $name;
        $this->redirectUri = $redirectUris;
    }

    public function getWriteAccess(): bool
    {
        return $this->writeAccess;
    }

    /**
     * @return non-empty-string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function isConfidential(): bool
    {
        return $this->confidential;
    }

    public function supportsGrantType(string $grantType): bool
    {
        return $this->grantTypes === null || \in_array($grantType, $this->grantTypes, true);
    }
}
