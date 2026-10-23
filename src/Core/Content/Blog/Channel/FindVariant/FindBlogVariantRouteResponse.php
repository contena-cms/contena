<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\FindVariant;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<FoundCombination>
 */
class FindBlogVariantRouteResponse extends StoreApiResponse
{
    public function getFoundCombination(): FoundCombination
    {
        return $this->object;
    }
}
