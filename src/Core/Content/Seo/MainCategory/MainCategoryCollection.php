<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\MainCategory;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MainCategoryEntity>
 *
 * @codeCoverageIgnore
 */
class MainCategoryCollection extends EntityCollection
{
    public function filterByChannelId(string $id): MainCategoryCollection
    {
        return $this->filter(static fn (MainCategoryEntity $mainCategory) => $mainCategory->getChannelId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'seo_main_category_collection';
    }

    protected function getExpectedClass(): string
    {
        return MainCategoryEntity::class;
    }
}
