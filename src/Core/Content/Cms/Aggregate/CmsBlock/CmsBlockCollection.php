<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Aggregate\CmsBlock;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CmsBlockEntity>
 */
class CmsBlockCollection extends EntityCollection
{
    public function getSlots(): CmsSlotCollection
    {
        $slots = new CmsSlotCollection();

        foreach ($this->getIterator() as $block) {
            if (!$block->getSlots()) {
                continue;
            }

            $slots->merge($block->getSlots());
        }

        return $slots;
    }

    public function filterBySectionPosition(string $position): CmsBlockCollection
    {
        return $this->filter(static fn (CmsBlockEntity $entity) => $entity->getSectionPosition() === $position);
    }

    public function setSlots(CmsSlotCollection $slots): void
    {
        foreach ($this->getIterator() as $block) {
            $blockSlots = $block->getSlots();
            if (!$blockSlots) {
                continue;
            }

            foreach ($blockSlots->getIds() as $slotId) {
                if ($slot = $slots->get($slotId)) {
                    $blockSlots->set($slotId, $slot);
                }
            }
        }
    }

    public function getApiAlias(): string
    {
        return 'cms_page_block_collection';
    }

    /**
     * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
     */
    public function hasBlockWithType(string $type): bool
    {
        return $this->firstWhere(static fn (CmsBlockEntity $block) => $block->getType() === $type) !== null;
    }

    protected function getExpectedClass(): string
    {
        return CmsBlockEntity::class;
    }
}
