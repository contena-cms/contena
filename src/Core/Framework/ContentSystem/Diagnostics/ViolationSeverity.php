<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Diagnostics;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum ViolationSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
