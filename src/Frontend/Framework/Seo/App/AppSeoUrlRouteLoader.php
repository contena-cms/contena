<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Seo\App;

use Contena\Core\Content\Seo\ConfiguredEntitySeoUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteLoaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
class AppSeoUrlRouteLoader implements SeoUrlRouteLoaderInterface
{
    public function __construct(
        private readonly AppSeoUrlRouteProvider $routes,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    public function load(): iterable
    {
        foreach ($this->routes->getEntityRoutes() as $seoUrl) {
            if (!$this->definitionRegistry->has($seoUrl->getEntityName())) {
                continue;
            }

            yield new ConfiguredEntitySeoUrlRoute(new AppSeoUrlRoute(
                $this->definitionRegistry->getByEntityName($seoUrl->getEntityName()),
                $seoUrl,
            ));
        }
    }
}
