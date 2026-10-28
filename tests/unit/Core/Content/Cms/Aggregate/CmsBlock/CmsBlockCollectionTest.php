<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\Aggregate\CmsBlock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsBlockCollection::class)]
class CmsBlockCollectionTest extends TestCase
{
    public function testGetAllSlotsFromBlocks(): void
    {
        $collection = new CmsBlockCollection();
        $collection->add($this->getBlock());
        $collection->add($this->getBlock());
        $collection->add($this->getBlock());

        static::assertCount(15, $collection->getSlots());
    }

    public function testSetAllSlotsInBlocks(): void
    {
        $collection = new CmsBlockCollection();
        $collection->add($this->getBlock());
        $collection->add($this->getBlock());
        $collection->add($this->getBlock());

        $slots = $collection->getSlots();

        /** @var CmsSlotEntity $slot */
        $slot = $slots->last();
        $slot->setConfig(['overwrite' => true]);

        $collection->setSlots($slots);

        /** @var CmsSlotEntity $lastSlot */
        $lastSlot = $collection->getSlots()->last();

        static::assertSame(['overwrite' => true], $lastSlot->getConfig());
    }

    private function getBlock(): CmsBlockEntity
    {
        $block = new CmsBlockEntity();
        $block->setUniqueIdentifier(Uuid::randomHex());
        $block->setType('block');

        $block->setSlots(new CmsSlotCollection([
            $this->getSlot(),
            $this->getSlot(),
            $this->getSlot(),
            $this->getSlot(),
            $this->getSlot(),
        ]));

        return $block;
    }

    private function getSlot(): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setType('slot');
        $slot->setConfig([]);
        $slot->setSlot(uniqid('', true));
        $slot->setUniqueIdentifier(Uuid::randomHex());

        return $slot;
    }
}
