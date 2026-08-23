<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Hreflang;

use Contena\Core\Framework\Struct\StructCollection;

/**
 * @extends StructCollection<HreflangStruct>
 */
class HreflangCollection extends StructCollection
{
    public function getApiAlias(): string
    {
        return 'seo_hreflang_collection';
    }

    protected function getExpectedClass(): string
    {
        return HreflangStruct::class;
    }
}
