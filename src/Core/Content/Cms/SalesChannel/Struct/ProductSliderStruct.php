<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\SalesChannel\Struct;

use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class ProductSliderStruct extends Struct
{
    protected ?ProductCollection $products = null;

    protected ?string $streamId = null;

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getApiAlias(): string
    {
        return 'cms_product_slider';
    }

    public function getStreamId(): ?string
    {
        return $this->streamId;
    }

    public function setStreamId(?string $streamId): void
    {
        $this->streamId = $streamId;
    }
}
