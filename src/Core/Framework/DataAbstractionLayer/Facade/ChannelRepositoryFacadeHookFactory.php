<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Facade;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAware;
use Contena\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;

/**
 * @internal
 */
class ChannelRepositoryFacadeHookFactory extends HookServiceFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ChannelDefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder
    ) {
    }

    public function factory(Hook $hook, Script $script): ChannelRepositoryFacade
    {
        if (!$hook instanceof ChannelContextAware) {
            throw DataAbstractionLayerException::hookInjectionException($hook, self::class, ChannelContextAware::class);
        }

        return new ChannelRepositoryFacade(
            $this->registry,
            $this->criteriaBuilder,
            $hook->getChannelContext()
        );
    }

    public function getName(): string
    {
        return 'store';
    }
}
