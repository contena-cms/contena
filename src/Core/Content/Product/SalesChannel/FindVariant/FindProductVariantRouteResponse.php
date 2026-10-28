<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\FindVariant;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<FoundCombination>
 */
#[Package('inventory')]
class FindProductVariantRouteResponse extends StoreApiResponse
{
    public function getFoundCombination(): FoundCombination
    {
        return $this->object;
    }
}
