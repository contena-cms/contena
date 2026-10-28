<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Aggregate\CategoryContentLayout;

use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class CategoryContentLayoutEntity extends AbstractContentLayoutAssignmentEntity
{
    protected string $categoryId;

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function setCategoryId(string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }
}
