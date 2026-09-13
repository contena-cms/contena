<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Seo\App;

use Contena\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
class AppSeoUrlRoute implements EntitySeoUrlRouteInterface
{
    public const TARGET_ROUTE = 'frontend.script_endpoint';

    public const PATH_PREFIX = '/frontend/script/';

    public function __construct(
        private readonly EntityDefinition $definition,
        private readonly string $routeName,
        private readonly string $hook,
        private readonly string $defaultTemplate,
    ) {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->definition,
            $this->routeName,
            $this->defaultTemplate,
            true,
            'id',
            self::TARGET_ROUTE,
            ['hook' => $this->hook]
        );
    }

    public function prepareCriteria(Criteria $criteria, ChannelEntity $channel): void
    {
    }
}
