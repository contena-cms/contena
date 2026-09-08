<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Api;

use Contena\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class ScriptResponseFactoryFacadeHookFactory extends HookServiceFactory
{
    public function __construct(
        private readonly RouterInterface $router,
    ) {
    }

    public function factory(Hook $hook, Script $script): ScriptResponseFactoryFacade
    {
        return new ScriptResponseFactoryFacade($this->router);
    }

    public function getName(): string
    {
        return 'response';
    }
}
