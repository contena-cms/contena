<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Aggregate\ProductContentLayout;

use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('inventory')]
class ProductContentLayoutEntity extends AbstractContentLayoutAssignmentEntity
{
    protected string $productId;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }
}
