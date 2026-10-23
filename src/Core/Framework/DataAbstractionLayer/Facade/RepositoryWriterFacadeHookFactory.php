<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Facade;

use Contena\Core\Framework\Api\Sync\SyncService;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Script\AppContextCreator;
use Contena\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
class RepositoryWriterFacadeHookFactory extends HookServiceFactory
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly AppContextCreator $appContextCreator,
        private readonly SyncService $syncService
    ) {
    }

    public function factory(Hook $hook, Script $script): RepositoryWriterFacade
    {
        return new RepositoryWriterFacade(
            $this->registry,
            $this->syncService,
            $this->appContextCreator->getAppContext($hook, $script)
        );
    }

    public function getName(): string
    {
        return 'writer';
    }
}
