<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Api;

use Contena\Core\Framework\DataAbstractionLayer\Facade\ChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Contena\Core\Framework\Routing\Facade\RequestFacadeFactory;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAware;
use Contena\Core\Framework\Script\Execution\Awareness\ScriptResponseAwareTrait;
use Contena\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Contena\Core\Framework\Script\Execution\Awareness\StoppableHookTrait;
use Contena\Core\Framework\Script\Execution\FunctionHook;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * Triggered when the api endpoint /channel-api/script/{hook} is called. Used to provide the HTTP-Response.
 * This function is only called when no response for the provided cache key is cached, or no `cache_key` function implemented.
 *
 * @hook-use-case custom_endpoint
 *
 * @since 6.4.9.0
 *
 * @final
 */
class ChannelApiResponseHook extends FunctionHook implements ChannelContextAware, StoppableHook
{
    use ScriptResponseAwareTrait;
    use StoppableHookTrait;

    final public const FUNCTION_NAME = 'response';

    /**
     * @param array<mixed> $request
     * @param array<mixed> $query
     */
    public function __construct(
        private readonly string $name,
        private readonly array $request,
        private readonly array $query,
        private readonly ChannelContext $channelContext
    ) {
        parent::__construct($channelContext->getContext());
    }

    /**
     * @return array<mixed>
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * @return array<mixed>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFunctionName(): string
    {
        return self::FUNCTION_NAME;
    }

    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            ChannelRepositoryFacadeHookFactory::class,
            RepositoryWriterFacadeHookFactory::class,
            ScriptResponseFactoryFacadeHookFactory::class,
            RequestFacadeFactory::class,
        ];
    }
}
