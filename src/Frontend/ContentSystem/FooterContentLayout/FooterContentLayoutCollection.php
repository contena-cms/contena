<?php declare(strict_types=1);

namespace Contena\Storefront\ContentSystem\FooterContentLayout;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @final
 *
 * @extends EntityCollection<FooterContentLayoutEntity>
 */
#[Package('framework')]
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
