<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth\Scope;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Contena\Core\Framework\Deprecation\BCChange\BecomesFinal;

#[BecomesFinal(version: 'v6.8.0')]
class AdminUserVerifiedScope implements ScopeEntityInterface
{
    final public const IDENTIFIER = 'admin-user-verified';

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function jsonSerialize(): mixed
    {
        return self::IDENTIFIER;
    }
}
