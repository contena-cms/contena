<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing\Filter;

use Contena\Core\Content\Blog\Channel\Listing\Filter;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class PriceListingFilterHandler extends AbstractListingFilterHandler
{
    final public const FILTER_ENABLED_REQUEST_PARAM = 'price-filter';

    public function getDecorated(): AbstractListingFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(Request $request, ChannelContext $context): ?Filter
    {
        if (!$request->request->get(self::FILTER_ENABLED_REQUEST_PARAM, true)) {
            return null;
        }

        $min = RequestParamHelper::get($request, 'min-price');
        $max = RequestParamHelper::get($request, 'max-price');

        $range = [];
        if ($min !== null && $min >= 0) {
            $range[RangeFilter::GTE] = $min;
        }
        if ($max !== null && $max >= 0) {
            $range[RangeFilter::LTE] = $max;
        }

        return new Filter(
            'price',
            $range !== [],
            [new StatsAggregation('price', 'blog.cheapestPrice', true, true, false, false)],
            new RangeFilter('blog.cheapestPrice', $range),
            [
                'min' => (float) RequestParamHelper::get($request, 'min-price'),
                'max' => (float) RequestParamHelper::get($request, 'max-price'),
            ]
        );
    }
}
