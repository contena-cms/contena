<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductStream;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductStreamEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductStreamCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_stream_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductStreamEntity::class;
    }
}
