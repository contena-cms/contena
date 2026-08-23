<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class AllowHtml
{
    public function __construct(public bool $sanitized = false)
    {
    }
}
