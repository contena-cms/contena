<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream\Aggregate\ContentStreamTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ContentStreamTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class ContentStreamTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getBlogStreamIds(): array
    {
        return $this->fmap(static fn (ContentStreamTranslationEntity $contentStreamTranslation) => $contentStreamTranslation->getBlogStreamId());
    }

    public function filterByBlogStreamId(string $id): self
    {
        return $this->filter(static fn (ContentStreamTranslationEntity $contentStreamTranslation) => $contentStreamTranslation->getBlogStreamId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (ContentStreamTranslationEntity $contentStreamTranslation) => $contentStreamTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (ContentStreamTranslationEntity $contentStreamTranslation) => $contentStreamTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'content_stream_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ContentStreamTranslationEntity::class;
    }
}
