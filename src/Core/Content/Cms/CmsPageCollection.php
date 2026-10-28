<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CmsPageEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CmsPageCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'cms_page_collection';
    }

    protected function getExpectedClass(): string
    {
        return CmsPageEntity::class;
    }
}
