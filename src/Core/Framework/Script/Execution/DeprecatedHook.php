<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Execution;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface DeprecatedHook
{
    public static function getDeprecationNotice(): string;
}
