<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ReferenceVersion extends Field
{
    public const string TYPE = 'reference-version';

    public function __construct(public string $entity, public ?string $column = null)
    {
        parent::__construct(type: self::TYPE, api: true, column: $column);
    }
}
