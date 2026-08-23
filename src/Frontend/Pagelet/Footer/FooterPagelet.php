<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Footer;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\Tree\Tree;
use Contena\Frontend\Pagelet\NavigationPagelet;

class FooterPagelet extends NavigationPagelet
{
    public function __construct(
        ?Tree $navigation,
        protected CategoryCollection $serviceMenu,
    ) {
        parent::__construct($navigation);
    }

    public function getServiceMenu(): CategoryCollection
    {
        return $this->serviceMenu;
    }
}
