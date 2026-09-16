<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Script\Api;

use Contena\Core\Framework\Script\Api\ScriptResponseFactoryFacade;
use Contena\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Frontend\Controller\ScriptController;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class FrontendScriptResponseFactoryFacadeHookFactory extends ScriptResponseFactoryFacadeHookFactory
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly ScriptController $scriptController,
    ) {
        parent::__construct($router);
    }

    public function factory(Hook $hook, Script $script): ScriptResponseFactoryFacade
    {
        \assert($hook instanceof FrontendHook);

        return new FrontendScriptResponseFactoryFacade($this->router, $this->scriptController);
    }
}
