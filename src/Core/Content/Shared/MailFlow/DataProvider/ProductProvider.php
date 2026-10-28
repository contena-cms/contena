<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<ProductEntity, ProductCollection>
 */
#[Package('after-sales')]
class ProductProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}
