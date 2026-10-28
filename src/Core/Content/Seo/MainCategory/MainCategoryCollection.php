<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\MainCategory;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MainCategoryEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class MainCategoryCollection extends EntityCollection
{
    public function filterBySalesChannelId(string $id): MainCategoryCollection
    {
        return $this->filter(static fn (MainCategoryEntity $mainCategory) => $mainCategory->getSalesChannelId() === $id);
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
