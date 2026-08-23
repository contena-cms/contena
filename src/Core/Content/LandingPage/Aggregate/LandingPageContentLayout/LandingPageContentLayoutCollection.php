<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\Aggregate\LandingPageContentLayout;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<LandingPageContentLayoutEntity>
 */
class LandingPageContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'landing_page_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return LandingPageContentLayoutEntity::class;
    }
}
