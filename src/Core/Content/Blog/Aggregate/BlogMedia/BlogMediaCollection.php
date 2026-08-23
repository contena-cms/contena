<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogMedia;

use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaType\SpatialObjectType;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogMediaEntity>
 */
class BlogMediaCollection extends EntityCollection
{
    /**
     * @return array<array-key, string>
     */
    public function getBlogIds(): array
    {
        return $this->fmap(static fn (BlogMediaEntity $blogMedia) => $blogMedia->getBlogId());
    }

    public function filterByBlogId(string $id): self
    {
        return $this->filter(static fn (BlogMediaEntity $blogMedia) => $blogMedia->getBlogId() === $id);
    }

    /**
     * @return array<array-key, string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(static fn (BlogMediaEntity $blogMedia) => $blogMedia->getMediaId());
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(static fn (BlogMediaEntity $blogMedia) => $blogMedia->getMediaId() === $id);
    }

    public function getMedia(): MediaCollection
    {
        return new MediaCollection(
            $this->fmap(static fn (BlogMediaEntity $blogMedia) => $blogMedia->getMedia())
        );
    }

    public function getApiAlias(): string
    {
        return 'blog_media_collection';
    }

    public function hasSpatialObjects(): bool
    {
        return $this->firstWhere(static fn (BlogMediaEntity $blogMedia) => $blogMedia->getMedia()?->getMediaType() instanceof SpatialObjectType) !== null;
    }

    protected function getExpectedClass(): string
    {
        return BlogMediaEntity::class;
    }
}
