<?php declare(strict_types=1);

namespace Contena\Frontend\ContentSystem\HeaderContentLayout;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<HeaderContentLayoutEntity>
 */
class HeaderContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'header_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return HeaderContentLayoutEntity::class;
    }
}
