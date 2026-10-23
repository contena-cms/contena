<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

use Contena\Core\Framework\Log\Package;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class AutoIncrement extends Field
{
    public const TYPE = 'auto-increment';

    public bool $nullable;

    public function __construct()
    {
        parent::__construct(type: self::TYPE, api: true);
    }
}
