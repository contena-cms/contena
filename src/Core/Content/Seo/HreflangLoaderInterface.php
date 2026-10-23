<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Contena\Core\Content\Seo\Hreflang\HreflangCollection;

interface HreflangLoaderInterface
{
    public function load(HreflangLoaderParameter $parameter): HreflangCollection;
}
