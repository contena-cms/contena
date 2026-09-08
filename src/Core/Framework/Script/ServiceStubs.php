<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script;

use Contena\Core\Framework\Script\Debugging\ScriptTraces;

/**
 * This class is intended for auto-completion in twig templates. So the developer can
 * set a doc block to get auto-completion for all services.
 *
 * @example: {# @var services \Contena\Core\Framework\Script\ServiceStubs #}
 *
 * @method \Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade repository()
 * @method \Contena\Core\System\SystemConfig\Facade\SystemConfigFacade config()
 * @method \Contena\Core\Framework\DataAbstractionLayer\Facade\ChannelRepositoryFacade store()
 * @method \Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacade writer()
 * @method \Contena\Core\Framework\Routing\Facade\RequestFacade request()
 * @method \Contena\Core\Framework\Script\Api\ScriptResponseFactoryFacade response()
 * @method \Contena\Core\Framework\Adapter\Cache\Script\Facade\CacheInvalidatorFacade cache()
 * @method \Contena\Core\Framework\Script\Api\AclFacade acl()
 */
final class ServiceStubs
{
    /**
     * @var array<string, array{deprecation?: string, service: object}>
     */
    private array $services = [];

    /**
     * @internal
     */
    public function __construct(private readonly string $hook)
    {
    }

    /**
     * @param array<mixed> $arguments
     *
     * @internal
     *
     * @param array<mixed> $arguments
     */
    public function __call(string $name, array $arguments): object
    {
        if (!isset($this->services[$name])) {
            throw ScriptException::serviceNotAvailableInHook($name, $this->hook);
        }

        if (isset($this->services[$name]['deprecation'])) {
            ScriptTraces::addDeprecationNotice($this->services[$name]['deprecation']);
        }

        return $this->services[$name]['service'];
    }

    /**
     * @internal
     */
    public function add(string $name, object $service, ?string $deprecationNotice = null): void
    {
        if (isset($this->services[$name])) {
            throw ScriptException::serviceAlreadyExists($name);
        }

        $this->services[$name]['service'] = $service;

        if ($deprecationNotice) {
            $this->services[$name]['deprecation'] = $deprecationNotice;
        }
    }

    /**
     * @internal
     */
    public function get(string $name): object
    {
        if (!isset($this->services[$name])) {
            throw ScriptException::serviceNotAvailableInHook($name, $this->hook);
        }

        if (isset($this->services[$name]['deprecation'])) {
            ScriptTraces::addDeprecationNotice($this->services[$name]['deprecation']);
        }

        return $this->services[$name]['service'];
    }
}
