<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet;

use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\Framework\Log\Package;

abstract class NavigationPagelet extends Pagelet
{
    public function __construct(
        protected ?Tree $navigation,
    ) {
    }

    public function getNavigation(): ?Tree
    {
        return $this->navigation;
    }
}
