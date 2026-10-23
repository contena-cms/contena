<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Service;

use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\System\Channel\ChannelContext;

interface NavigationLoaderInterface
{
    public const DEFAULT_DEPTH = 2;

    /**
     * Returns the first two levels of the category tree, as well as all parents of the active category
     * and the active categories first level of children.
     * The provided active id will be marked as selected
     */
    public function load(string $activeId, ChannelContext $context, string $rootId, int $depth = self::DEFAULT_DEPTH): Tree;
}
