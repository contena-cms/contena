<?php declare(strict_types=1);

namespace Contena\Core\Content\Media;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MediaEntity>
 */
class MediaCollection extends EntityCollection
{
    /**
     * @return array<array-key, string>
     */
    public function getUserIds(): array
    {
        return $this->fmap(static fn (MediaEntity $media) => $media->getUserId());
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(static fn (MediaEntity $media) => $media->getUserId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'media_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaEntity::class;
    }
}
