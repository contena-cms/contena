<?php declare(strict_types=1);

namespace Contena\Storefront\Page;

use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\Facade\RequestFacadeFactory;
use Contena\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Contena\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal only rely on the concrete implementations
 */
#[Package('discovery')]
abstract class PageLoadedHook extends Hook implements SalesChannelContextAware
{
    /**
     * @return list<class-string<HookServiceFactory>>
     */
    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            SalesChannelRepositoryFacadeHookFactory::class,
            RequestFacadeFactory::class,
        ];
    }
}
