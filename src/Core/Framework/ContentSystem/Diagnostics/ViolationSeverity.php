<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Diagnostics;

/**
 * @internal
 */
enum ViolationSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
