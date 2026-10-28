<?php declare(strict_types=1);

namespace Contena\Storefront\ContentSystem\HeaderContentLayout;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @final
 *
 * @extends EntityCollection<HeaderContentLayoutEntity>
 */
#[Package('framework')]
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
