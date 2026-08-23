<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class AutoIncrement extends Field
{
    public const string TYPE = 'auto-increment';

    public bool $nullable;

    public function __construct()
    {
        parent::__construct(type: self::TYPE, api: true);
    }
}
