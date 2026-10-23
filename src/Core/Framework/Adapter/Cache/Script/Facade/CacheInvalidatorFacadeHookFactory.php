<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Script\Facade;

use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
class CacheInvalidatorFacadeHookFactory extends HookServiceFactory
{
    public function __construct(private readonly CacheInvalidator $cacheInvalidator)
    {
    }

    public function factory(Hook $hook, Script $script): CacheInvalidatorFacade
    {
        return new CacheInvalidatorFacade($this->cacheInvalidator);
    }

    public function getName(): string
    {
        return 'cache';
    }
}
