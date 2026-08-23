<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Util;

use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
class CategoryBreadcrumbHelper
{
    /**
     * @return array<string, string>|null
     */
    public static function build(CategoryEntity $category, ?ChannelEntity $channel = null, ?string $navigationCategoryId = null): ?array
    {
        $categoryBreadcrumb = $category->getPlainBreadcrumb();

        if ($channel === null && $navigationCategoryId === null) {
            return $categoryBreadcrumb;
        }

        $entryPoints = [$navigationCategoryId];

        if ($channel !== null) {
            $entryPoints[] = $channel->getNavigationCategoryId();
            $entryPoints[] = $channel->getServiceCategoryId();
            $entryPoints[] = $channel->getFooterCategoryId();
        }

        $keys = array_keys($categoryBreadcrumb);

        foreach (array_filter($entryPoints) as $entryPoint) {
            $position = array_search($entryPoint, $keys, true);

            if ($position !== false) {
                return \array_slice($categoryBreadcrumb, $position + 1);
            }
        }

        return $categoryBreadcrumb;
    }
}
