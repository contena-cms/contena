<?php declare(strict_types=1);

namespace Contena\Frontend\Page;

use Contena\Core\Framework\DataAbstractionLayer\Facade\ChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\Routing\Facade\RequestFacadeFactory;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAware;
use Contena\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal only rely on the concrete implementations
 */
abstract class PageLoadedHook extends Hook implements ChannelContextAware
{
    /**
     * @return list<class-string<HookServiceFactory>>
     */
    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            ChannelRepositoryFacadeHookFactory::class,
            RequestFacadeFactory::class,
        ];
    }
}
