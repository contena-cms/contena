<?php declare(strict_types=1);

namespace Contena\Frontend\ContentSystem\FooterContentLayout;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<FooterContentLayoutEntity>
 */
class FooterContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'footer_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return FooterContentLayoutEntity::class;
    }
}
