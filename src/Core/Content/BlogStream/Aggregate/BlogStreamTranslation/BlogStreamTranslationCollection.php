<?php declare(strict_types=1);

namespace Contena\Core\Content\BlogStream\Aggregate\BlogStreamTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogStreamTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class BlogStreamTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getBlogStreamIds(): array
    {
        return $this->fmap(static fn (BlogStreamTranslationEntity $blogStreamTranslation) => $blogStreamTranslation->getBlogStreamId());
    }

    public function filterByBlogStreamId(string $id): self
    {
        return $this->filter(static fn (BlogStreamTranslationEntity $blogStreamTranslation) => $blogStreamTranslation->getBlogStreamId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (BlogStreamTranslationEntity $blogStreamTranslation) => $blogStreamTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (BlogStreamTranslationEntity $blogStreamTranslation) => $blogStreamTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'blog_stream_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogStreamTranslationEntity::class;
    }
}
