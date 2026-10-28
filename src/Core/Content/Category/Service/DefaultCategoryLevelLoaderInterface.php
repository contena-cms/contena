<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Service;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal only for internal use as it only loads the default category levels
 * externals should rely on the @see NavigationLoader
 */
#[Package('discovery')]
interface DefaultCategoryLevelLoaderInterface
{
    public function loadLevels(
        string $rootId,
        int $rootLevel,
        SalesChannelContext $context,
        Criteria $criteria,
        int $depth,
    ): CategoryCollection;
}
