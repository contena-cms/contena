<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Script\Api;

use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\Facade\RequestFacadeFactory;
use Contena\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Contena\Core\Framework\Script\Execution\Awareness\ScriptResponseAwareTrait;
use Contena\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Contena\Core\Framework\Script\Execution\Awareness\StoppableHookTrait;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;
use Contena\Storefront\Page\Page;

/**
 * Triggered when the storefront endpoint /storefront/script/{hook} is called
 *
 * @hook-use-case custom_endpoint
 *
 * @since 6.4.9.0
 *
 * @final
 */
#[Package('discovery')]
class StorefrontHook extends Hook implements SalesChannelContextAware, StoppableHook
{
    use ScriptResponseAwareTrait;
    use StoppableHookTrait;

    final public const HOOK_NAME = 'storefront-{hook}';

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $query
     */
    public function __construct(
        private readonly string $script,
        private readonly array $request,
        private readonly array $query,
        private readonly Page $page,
        private readonly SalesChannelContext $salesChannelContext
    ) {
        parent::__construct($salesChannelContext->getContext());
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

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getName(): string
    {
        return \str_replace(
            ['{hook}'],
            [$this->script],
            self::HOOK_NAME
        );
    }

    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            SalesChannelRepositoryFacadeHookFactory::class,
            RepositoryWriterFacadeHookFactory::class,
            StorefrontScriptResponseFactoryFacadeHookFactory::class,
            RequestFacadeFactory::class,
        ];
    }

    public function getPage(): Page
    {
        return $this->page;
    }
}
