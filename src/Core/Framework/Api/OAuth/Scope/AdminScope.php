<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth\Scope;

use League\OAuth2\Server\Entities\ScopeEntityInterface;

class AdminScope implements ScopeEntityInterface
{
    final public const string IDENTIFIER = 'admin';

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function jsonSerialize(): mixed
    {
        return self::IDENTIFIER;
    }
}
