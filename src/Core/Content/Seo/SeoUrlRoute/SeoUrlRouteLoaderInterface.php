<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlRoute;

use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
interface SeoUrlRouteLoaderInterface
{
    /**
     * @return iterable<SeoUrlRouteInterface>
     */
    public function load(): iterable;
}
