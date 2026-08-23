<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Password extends Field
{
    public const string TYPE = 'password';

    /**
     * @param array<string, mixed> $hashOptions
     */
    public function __construct(
        public ?string $algorithm = \PASSWORD_DEFAULT,
        public array $hashOptions = [],
        public ?string $for = null,
        public bool|array $api = false,
        public ?string $column = null,
    ) {
        parent::__construct(type: self::TYPE, api: $api, column: $column);
    }
}
