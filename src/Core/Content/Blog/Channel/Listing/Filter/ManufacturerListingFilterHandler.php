<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing\Filter;

use Contena\Core\Content\Blog\Channel\Listing\Filter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ManufacturerListingFilterHandler extends AbstractListingFilterHandler
{
    final public const FILTER_ENABLED_REQUEST_PARAM = 'manufacturer-filter';

    public function getDecorated(): AbstractListingFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(Request $request, ChannelContext $context): ?Filter
    {
        if (!$request->request->get(self::FILTER_ENABLED_REQUEST_PARAM, true)) {
            return null;
        }

        $ids = $this->getManufacturerIds($request);

        return new Filter(
            'manufacturer',
            $ids !== [],
            [new EntityAggregation('manufacturer', 'blog.manufacturerId', 'blog_manufacturer')],
            new EqualsAnyFilter('blog.manufacturerId', $ids),
            $ids
        );
    }

    /**
     * @return list<string>
     */
    private function getManufacturerIds(Request $request): array
    {
        $ids = $request->query->get('manufacturer', '');
        if ($request->isMethod(Request::METHOD_POST)) {
            $ids = $request->request->get('manufacturer', '');
        }

        if (\is_string($ids)) {
            $ids = explode('|', $ids);
        }

        /** @var list<non-falsy-string> $ids */
        $ids = array_filter((array) $ids);

        return $ids;
    }
}
