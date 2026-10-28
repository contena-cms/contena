<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Contena\Core\Content\Seo\Hreflang\HreflangCollection;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
interface HreflangLoaderInterface
{
    public function load(HreflangLoaderParameter $parameter): HreflangCollection;
}
