<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\DataAbstractionLayer;

use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;

class CategoryIndexingMessage extends EntityIndexingMessage
{
    /**
     * Ids that are only re-indexed to recompute their child count (the parents of
     * a created/deleted/moved category). They must be excluded from the recursive
     * tree update, which would otherwise walk their whole subtree.
     *
     * @var array<string>
     */
    private array $childCountOnlyIds = [];

    /**
     * @param array<string> $ids
     */
    public function setChildCountOnlyIds(array $ids): void
    {
        $this->childCountOnlyIds = $ids;
    }

    /**
     * @return array<string>
     */
    public function getChildCountOnlyIds(): array
    {
        return $this->childCountOnlyIds;
    }
}
