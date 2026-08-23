<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Diagnostics;

/**
 * @internal
 */
enum ViolationScope: string
{
    case Intrinsic = 'intrinsic';
    case Binding = 'binding';
}
