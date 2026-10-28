<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Navigation\Error;

use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Page\Page;

#[Package('discovery')]
class ErrorPage extends Page
{



    public function isErrorPage(): bool
    {
        return true;
    }
}
