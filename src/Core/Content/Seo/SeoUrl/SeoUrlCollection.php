<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrl;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SeoUrlEntity>
 */
class SeoUrlCollection extends EntityCollection
{
    public function filterByChannelId(string $id): SeoUrlCollection
    {
        return $this->filter(static fn (SeoUrlEntity $seoUrl) => $seoUrl->getChannelId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'seo_url_collection';
    }

    protected function getExpectedClass(): string
    {
        return SeoUrlEntity::class;
    }
}
