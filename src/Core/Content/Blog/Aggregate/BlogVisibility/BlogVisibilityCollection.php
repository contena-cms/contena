<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogVisibility;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogVisibilityEntity>
 */
class BlogVisibilityCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getBlogIds(): array
    {
        return $this->fmap(static fn (BlogVisibilityEntity $visibility) => $visibility->getBlogId());
    }

    public function filterByBlogId(string $id): self
    {
        return $this->filter(static fn (BlogVisibilityEntity $visibility) => $visibility->getBlogId() === $id);
    }

    public function filterByChannelId(string $id): self
    {
        return $this->filter(static fn (BlogVisibilityEntity $visibility) => $visibility->getChannelId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'blog_visibility_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogVisibilityEntity::class;
    }
}
