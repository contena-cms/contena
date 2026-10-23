<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Seo\App;

use Contena\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
class AppSeoUrlRoute implements EntitySeoUrlRouteInterface
{
    public const TARGET_ROUTE = 'frontend.script_endpoint';

    private const ROUTE_NAME_PREFIX = 'frontend.app.';

    public function __construct(
        private readonly EntityDefinition $definition,
        private readonly AppEntitySeoUrlConfig $seoUrl,
    ) {
    }

    public static function buildRouteName(string $appName, string $name): string
    {
        return self::routeNamePrefix($appName) . $name;
    }

    public static function routeNamePrefix(string $appName): string
    {
        return self::ROUTE_NAME_PREFIX . $appName . '.';
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->definition,
            $this->seoUrl->getRouteName(),
            $this->seoUrl->getDefaultTemplate(),
            true,
            'id',
            self::TARGET_ROUTE,
            ['hook' => $this->seoUrl->getHook()]
        );
    }

    public function prepareCriteria(Criteria $criteria, ChannelEntity $channel): void
    {
    }
}
