<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ForeignKey extends Field
{
    public const string TYPE = 'fk';

    public bool $nullable;

    public function __construct(
        public string $entity,
        public bool|array $api = false,
        public ?string $column = null
    ) {
        parent::__construct(type: self::TYPE, api: $api, column: $column);
    }
}
