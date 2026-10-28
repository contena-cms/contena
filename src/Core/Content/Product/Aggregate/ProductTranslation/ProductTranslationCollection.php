<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Aggregate\ProductTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getProductIds(): array
    {
        return $this->fmap(static fn (ProductTranslationEntity $productTranslation) => $productTranslation->getProductId());
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(static fn (ProductTranslationEntity $productTranslation) => $productTranslation->getProductId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (ProductTranslationEntity $productTranslation) => $productTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (ProductTranslationEntity $productTranslation) => $productTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'product_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductTranslationEntity::class;
    }
}
