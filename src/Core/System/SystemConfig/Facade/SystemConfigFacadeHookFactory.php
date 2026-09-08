<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Facade;

use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAware;
use Contena\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
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
        $channelId = null;

        if ($hook instanceof ChannelContextAware) {
            $channelId = $hook->getChannelContext()->getChannelId();
        }

        return new SystemConfigFacade($this->systemConfigService, $this->connection, $script->getScriptAppInformation(), $channelId);
    }
}
