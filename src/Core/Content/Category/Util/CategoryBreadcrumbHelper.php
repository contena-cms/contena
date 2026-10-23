<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Util;

use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Framework\Log\Package;
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

        // If the current Channel is null ( which refers to the default template Channel) or
        // this category has no root, we return the full breadcrumb
        if ($channel === null && $navigationCategoryId === null) {
            return $categoryBreadcrumb;
        }

        $entryPoints = [
            $navigationCategoryId,
        ];

        if ($channel !== null) {
            $entryPoints[] = $channel->getNavigationCategoryId();
            $entryPoints[] = $channel->getServiceCategoryId();
            $entryPoints[] = $channel->getFooterCategoryId();
        }

        $entryPoints = array_filter($entryPoints);

        $keys = array_keys($categoryBreadcrumb);

        foreach ($entryPoints as $entryPoint) {
            // Check where this category is located in relation to the navigation entry point of the sales channel
            $pos = array_search($entryPoint, $keys, true);

            if ($pos !== false) {
                // Remove all breadcrumbs preceding the navigation category
                return \array_slice($categoryBreadcrumb, $pos + 1);
            }
        }

        return $categoryBreadcrumb;
    }
}
