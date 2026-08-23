<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

use Contena\Core\Framework\Context;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class State extends Field
{
    public const string TYPE = 'state';

    /**
     * @param array<string> $scopes
     */
    public function __construct(
        public string $machine,
        public array $scopes = [Context::SYSTEM_SCOPE],
        bool|array $api = false,
        ?string $column = null
    ) {
        parent::__construct(type: self::TYPE, api: $api, column: $column);
    }
}
