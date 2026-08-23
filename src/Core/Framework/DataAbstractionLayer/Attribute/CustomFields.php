<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CustomFields extends Field
{
    public const string TYPE = 'custom-fields';

    public function __construct(public bool $translated = false, public ?string $column = null)
    {
        parent::__construct(type: self::TYPE, translated: $this->translated, api: true, column: $column);
    }
}
