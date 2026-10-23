<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogManufacturer;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogManufacturerEntity>
 *
 * @codeCoverageIgnore
 */
class BlogManufacturerCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(static fn (BlogManufacturerEntity $blogManufacturer) => $blogManufacturer->getMediaId());
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(static fn (BlogManufacturerEntity $blogManufacturer) => $blogManufacturer->getMediaId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'blog_manufacturer_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogManufacturerEntity::class;
    }
}
