<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Facade;

use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Script\AppContextCreator;
use Contena\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
class RepositoryFacadeHookFactory extends HookServiceFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly AppContextCreator $appContextCreator,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly AclCriteriaValidator $criteriaValidator
    ) {
    }

    public function factory(Hook $hook, Script $script): RepositoryFacade
    {
        return new RepositoryFacade(
            $this->registry,
            $this->criteriaBuilder,
            $this->criteriaValidator,
            $this->appContextCreator->getAppContext($hook, $script)
        );
    }

    public function getName(): string
    {
        return 'repository';
    }
}
