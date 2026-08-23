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
    public function __construct(
        private readonly string $identifier,
        private readonly bool $writeAccess,
        private readonly bool $confidential,
        string $name = '',
    ) {
        $this->name = $name;
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
}
