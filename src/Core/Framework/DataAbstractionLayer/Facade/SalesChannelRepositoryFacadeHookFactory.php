<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Facade;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Contena\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelRepositoryFacadeHookFactory extends HookServiceFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelDefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder
    ) {
    }

    public function factory(Hook $hook, Script $script): SalesChannelRepositoryFacade
    {
        if (!$hook instanceof SalesChannelContextAware) {
            throw DataAbstractionLayerException::hookInjectionException($hook, self::class, SalesChannelContextAware::class);
        }

        return new SalesChannelRepositoryFacade(
            $this->registry,
            $this->criteriaBuilder,
            $hook->getSalesChannelContext()
        );
    }

    public function getName(): string
    {
        return 'store';
    }
}
