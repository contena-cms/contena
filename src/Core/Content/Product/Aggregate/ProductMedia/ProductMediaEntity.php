<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Aggregate\ProductMedia;

use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductMediaEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $productId;

    protected string $mediaId;

    protected int $position;

    protected ?MediaEntity $media = null;

    protected ?ProductEntity $product = null;

    protected ?ProductCollection $coverProducts = null;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function setMediaId(string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getCoverProducts(): ?ProductCollection
    {
        return $this->coverProducts;
    }

    public function setCoverProducts(ProductCollection $coverProducts): void
    {
        $this->coverProducts = $coverProducts;
    }
}
