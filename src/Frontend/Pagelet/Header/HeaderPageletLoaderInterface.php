<?php declare(strict_types=1);

namespace Contena\Storefront\Pagelet\Header;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
interface HeaderPageletLoaderInterface
{
    public function load(Request $request, SalesChannelContext $context): HeaderPagelet;
}
