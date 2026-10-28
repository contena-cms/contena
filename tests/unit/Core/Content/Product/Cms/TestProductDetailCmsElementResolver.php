<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\Cms;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Product\Cms\AbstractProductDetailCmsElementResolver;
use Contena\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class TestProductDetailCmsElementResolver extends AbstractProductDetailCmsElementResolver
{
    public function getType(): string
    {
        return 'test';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        // nth
    }

    public function runGetSlotProduct(CmsSlotEntity $slot, ElementDataCollection $result, string $productId): ?SalesChannelProductEntity
    {
        return $this->getSlotProduct($slot, $result, $productId);
    }
}
