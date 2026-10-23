<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlRoute;


interface SeoUrlRouteLoaderInterface
{
    /**
     * @return iterable<SeoUrlRouteInterface>
     */
    public function load(): iterable;
}
