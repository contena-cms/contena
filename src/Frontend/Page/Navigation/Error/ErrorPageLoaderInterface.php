<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Navigation\Error;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
interface ErrorPageLoaderInterface
{
    public function load(string $cmsErrorLayoutId, Request $request, SalesChannelContext $context): ErrorPage;
}
