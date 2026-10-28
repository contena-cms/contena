<?php declare(strict_types=1);

namespace Contena\Storefront\Page;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
interface GenericPageLoaderInterface
{
    public function load(Request $request, SalesChannelContext $context): Page;
}
