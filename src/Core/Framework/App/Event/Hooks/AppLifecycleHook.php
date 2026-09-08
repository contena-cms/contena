<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event\Hooks;

use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal only rely on the concrete hook implementations
 */
abstract class AppLifecycleHook extends Hook
{
    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            RepositoryWriterFacadeHookFactory::class,
        ];
    }
}
