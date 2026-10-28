<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event\Hooks;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\TraceHook;

/**
 * @internal
 */
#[Package('framework')]
class AppScriptConditionHook extends TraceHook
{
    public static function getServiceIds(): array
    {
        return [];
    }

    public function getName(): string
    {
        return 'rule-conditions';
    }
}
