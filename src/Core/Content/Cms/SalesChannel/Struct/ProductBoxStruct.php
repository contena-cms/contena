<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\SalesChannel\Struct;

use Contena\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class ProductBoxStruct extends Struct
{
    protected ?string $productId = null;

    protected ?SalesChannelProductEntity $product = null;

    public function getProduct(): ?SalesChannelProductEntity
    {
        return $this->product;
    }

    public function setProduct(SalesChannelProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getApiAlias(): string
    {
        return 'cms_product_box';
    }
}
