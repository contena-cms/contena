<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Element\Slot;

use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\Struct\Collection;

/**
 * @final
 *
 * @extends Collection<ContentElement>
 */
class SlotContent extends Collection
{
    public function getApiAlias(): string
    {
        return 'content_element_slot_content';
    }

    protected function getExpectedClass(): string
    {
        return ContentElement::class;
    }
}
