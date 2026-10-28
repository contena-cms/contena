<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth\Scope;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Contena\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
#[BecomesFinal(version: 'v6.8.0')]
class WriteScope implements ScopeEntityInterface
{
    final public const IDENTIFIER = 'write';

    /**
     * Get the scope's identifier.
     */
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function jsonSerialize(): mixed
    {
        return self::IDENTIFIER;
    }
}
