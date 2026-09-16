<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Script\Api;

use Contena\Core\Framework\DataAbstractionLayer\Facade\ChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Contena\Core\Framework\Routing\Facade\RequestFacadeFactory;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAware;
use Contena\Core\Framework\Script\Execution\Awareness\ScriptResponseAwareTrait;
use Contena\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Contena\Core\Framework\Script\Execution\Awareness\StoppableHookTrait;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;
use Contena\Frontend\Page\Page;

/**
 * Triggered when the frontend endpoint /frontend/script/{hook} is called.
 *
 * @hook-use-case custom_endpoint
 *
 * @final
 */
class FrontendHook extends Hook implements ChannelContextAware, StoppableHook
{
    use ScriptResponseAwareTrait;
    use StoppableHookTrait;

    final public const string HOOK_NAME = 'frontend-{hook}';

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $query
     */
    public function __construct(
        private readonly string $script,
        private readonly array $request,
        private readonly array $query,
        private readonly Page $page,
        private readonly ChannelContext $channelContext,
    ) {
        parent::__construct($channelContext->getContext());
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * @return array<string, mixed>
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
        return \str_replace('{hook}', $this->script, self::HOOK_NAME);
    }

    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            ChannelRepositoryFacadeHookFactory::class,
            RepositoryWriterFacadeHookFactory::class,
            FrontendScriptResponseFactoryFacadeHookFactory::class,
            RequestFacadeFactory::class,
        ];
    }

    public function getPage(): Page
    {
        return $this->page;
    }
}
