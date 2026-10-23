<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing\Filter;

use Contena\Core\Content\Blog\Channel\Listing\Filter;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

class RatingListingFilterHandler extends AbstractListingFilterHandler
{
    final public const FILTER_ENABLED_REQUEST_PARAM = 'rating-filter';

    public function getDecorated(): AbstractListingFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(Request $request, ChannelContext $context): ?Filter
    {
        if (!$request->request->get(self::FILTER_ENABLED_REQUEST_PARAM, true)) {
            return null;
        }

        $filtered = RequestParamHelper::get($request, 'rating');

        return new Filter(
            'rating',
            $filtered !== null,
            [
            ],
            new RangeFilter('blog.ratingAverage', [
                RangeFilter::GTE => (int) $filtered,
            ]),
            $filtered
        );
    }
}
