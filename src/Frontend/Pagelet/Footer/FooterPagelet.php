<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Footer;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Frontend\Pagelet\NavigationPagelet;

/**
 * @codeCoverageIgnore
 */
class FooterPagelet extends NavigationPagelet
{

    public function getServiceMenu(): CategoryCollection
    {
        return $this->serviceMenu;
    }


}
