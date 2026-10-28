<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Facade;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Contena\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
class SystemConfigFacadeHookFactory extends HookServiceFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection
    ) {
    }

    public function getName(): string
    {
        return 'config';
    }

    public function factory(Hook $hook, Script $script): SystemConfigFacade
    {
        $salesChannelId = null;

        if ($hook instanceof SalesChannelContextAware) {
            $salesChannelId = $hook->getSalesChannelContext()->getSalesChannelId();
        }

        return new SystemConfigFacade($this->systemConfigService, $this->connection, $script->getScriptAppInformation(), $salesChannelId);
    }
}
