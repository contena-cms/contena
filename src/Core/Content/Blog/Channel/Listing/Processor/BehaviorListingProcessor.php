<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing\Processor;

use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class BehaviorListingProcessor extends AbstractListingProcessor
{
    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, ChannelContext $context): void
    {
        if (RequestParamHelper::get($request, 'no-aggregations')) {
            $criteria->resetAggregations();
        }

        if (RequestParamHelper::get($request, 'only-aggregations')) {
            // set limit to zero to fetch no blogs.
            $criteria->setLimit(0);

            // no total count required
            $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

            // sorting and association are only required for the blog data
            $criteria->resetSorting();
            $criteria->resetAssociations();
        }
    }
}
