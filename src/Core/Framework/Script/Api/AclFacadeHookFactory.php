<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Api;

use Contena\Core\Framework\Script\AppContextCreator;
use Contena\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
class AclFacadeHookFactory extends HookServiceFactory
{
    /**
     * @internal
     */
    public function __construct(private readonly AppContextCreator $appContextCreator)
    {
    }

    public function factory(Hook $hook, Script $script): AclFacade
    {
        return new AclFacade(
            $this->appContextCreator->getAppContext($hook, $script)
        );
    }

    public function getName(): string
    {
        return 'acl';
    }
}
