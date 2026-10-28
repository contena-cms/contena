<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductExportEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductExportCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_export_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductExportEntity::class;
    }
}
