<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogTranslationEntity>
 */
class BlogTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getBlogIds(): array
    {
        return $this->fmap(static fn (BlogTranslationEntity $blogTranslation) => $blogTranslation->getBlogId());
    }

    public function filterByBlogId(string $id): self
    {
        return $this->filter(static fn (BlogTranslationEntity $blogTranslation) => $blogTranslation->getBlogId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (BlogTranslationEntity $blogTranslation) => $blogTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (BlogTranslationEntity $blogTranslation) => $blogTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'blog_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogTranslationEntity::class;
    }
}
