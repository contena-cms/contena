<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\SalesChannel\Struct;

use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class ProductListingStruct extends Struct
{
    /**
     * @var EntitySearchResult<ProductCollection>|null
     */
    protected ?EntitySearchResult $listing = null;

    /**
     * @return EntitySearchResult<ProductCollection>|null
     */
    public function getListing(): ?EntitySearchResult
    {
        return $this->listing;
    }

    /**
     * @param EntitySearchResult<ProductCollection> $listing
     */
    public function setListing(EntitySearchResult $listing): void
    {
        $this->listing = $listing;
    }

    public function getApiAlias(): string
    {
        return 'cms_product_listing';
    }
}
