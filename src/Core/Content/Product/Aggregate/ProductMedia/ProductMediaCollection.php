<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Aggregate\ProductMedia;

use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaType\SpatialObjectType;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductMediaEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductMediaCollection extends EntityCollection
{
    /**
     * @return array<array-key, string>
     */
    public function getProductIds(): array
    {
        return $this->fmap(static fn (ProductMediaEntity $productMedia) => $productMedia->getProductId());
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(static fn (ProductMediaEntity $productMedia) => $productMedia->getProductId() === $id);
    }

    /**
     * @return array<array-key, string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(static fn (ProductMediaEntity $productMedia) => $productMedia->getMediaId());
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(static fn (ProductMediaEntity $productMedia) => $productMedia->getMediaId() === $id);
    }

    public function getMedia(): MediaCollection
    {
        return new MediaCollection(
            $this->fmap(static fn (ProductMediaEntity $productMedia) => $productMedia->getMedia())
        );
    }

    public function getApiAlias(): string
    {
        return 'product_media_collection';
    }

    public function hasSpatialObjects(): bool
    {
        return $this->firstWhere(static fn (ProductMediaEntity $productMedia) => $productMedia->getMedia()?->getMediaType() instanceof SpatialObjectType) !== null;
    }

    protected function getExpectedClass(): string
    {
        return ProductMediaEntity::class;
    }
}
