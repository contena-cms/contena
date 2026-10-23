<?php declare(strict_types=1);

namespace Contena\Core\Test\Annotation;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class DisabledFeatures
{
    /**
     * @param array<string> $features
     */
    public function __construct(public array $features = [])
    {
    }
}
