<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlRoute;

/**
 * @internal
 */
interface SeoUrlRouteLoaderInterface
{
    /**
     * @return iterable<SeoUrlRouteInterface>
     */
    public function load(): iterable;
}
