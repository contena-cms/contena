<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Listing\Filter;

use Contena\Core\Content\Product\SalesChannel\Listing\Filter;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class PriceListingFilterHandler extends AbstractListingFilterHandler
{
    final public const FILTER_ENABLED_REQUEST_PARAM = 'price-filter';

    public function getDecorated(): AbstractListingFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(Request $request, SalesChannelContext $context): ?Filter
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
            [new StatsAggregation('price', 'product.cheapestPrice', true, true, false, false)],
            new RangeFilter('product.cheapestPrice', $range),
            [
                'min' => (float) RequestParamHelper::get($request, 'min-price'),
                'max' => (float) RequestParamHelper::get($request, 'max-price'),
            ]
        );
    }
}
