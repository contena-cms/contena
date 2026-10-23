<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogManufacturerTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogManufacturerTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class BlogManufacturerTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getBlogManufacturerIds(): array
    {
        return $this->fmap(static fn (BlogManufacturerTranslationEntity $blogManufacturerTranslation) => $blogManufacturerTranslation->getBlogManufacturerId());
    }

    public function filterByBlogManufacturerId(string $id): self
    {
        return $this->filter(static fn (BlogManufacturerTranslationEntity $blogManufacturerTranslation) => $blogManufacturerTranslation->getBlogManufacturerId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (BlogManufacturerTranslationEntity $blogManufacturerTranslation) => $blogManufacturerTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (BlogManufacturerTranslationEntity $blogManufacturerTranslation) => $blogManufacturerTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'blog_manufacturer_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogManufacturerTranslationEntity::class;
    }
}
