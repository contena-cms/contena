<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

use Contena\Core\Framework\Context;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Protection
{
    final public const string SYSTEM_SCOPE = Context::SYSTEM_SCOPE;
    final public const string USER_SCOPE = Context::USER_SCOPE;
    final public const string CRUD_API_SCOPE = Context::CRUD_API_SCOPE;

    /**
     * @param array<string> $write
     */
    public function __construct(public array $write)
    {
    }
}
