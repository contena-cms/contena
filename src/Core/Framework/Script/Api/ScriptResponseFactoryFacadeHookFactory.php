<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Api;

use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAware;
use Contena\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Frontend\Controller\ScriptController;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class ScriptResponseFactoryFacadeHookFactory extends HookServiceFactory
{
    public function __construct(
        private readonly RouterInterface $router,
        /**
         * @deprecated tag:v6.8.0 - only needed for the deprecated render() BC path.
         *
         * @phpstan-ignore phpat.restrictNamespacesInCore (Frontend dependency is nullable. Don't do that! Will be removed with v6.8.0 when render() is removed from the core response facade)
         */
        private readonly ?ScriptController $scriptController = null,
    ) {
    }

    public function factory(Hook $hook, Script $script): ScriptResponseFactoryFacade
    {
        // @deprecated tag:v6.8.0 - only needed for the deprecated render() BC path.
        $channelContext = $hook instanceof ChannelContextAware ? $hook->getChannelContext() : null;

        return new ScriptResponseFactoryFacade(
            $this->router,
            $this->scriptController,
            $channelContext
        );
    }

    public function getName(): string
    {
        return 'response';
    }
}
