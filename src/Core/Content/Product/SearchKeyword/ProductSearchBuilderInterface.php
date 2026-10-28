<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SearchKeyword;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
interface ProductSearchBuilderInterface
{
    public function build(Request $request, Criteria $criteria, SalesChannelContext $context): void;
}
