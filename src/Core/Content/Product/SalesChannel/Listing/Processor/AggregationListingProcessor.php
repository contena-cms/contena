<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Listing\Processor;

use Contena\Core\Content\Product\Events\ProductListingCollectFilterEvent;
use Contena\Core\Content\Product\SalesChannel\Listing\Filter\AbstractListingFilterHandler;
use Contena\Core\Content\Product\SalesChannel\Listing\FilterCollection;
use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class AggregationListingProcessor extends AbstractListingProcessor
{
    /**
     * @param iterable<AbstractListingFilterHandler> $factories
     *
     * @internal
     */
    public function __construct(
        private readonly iterable $factories,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $filters = $this->getFilters($request, $context);

        $aggregations = $this->getAggregations($request, $filters);

        foreach ($aggregations as $aggregation) {
            $criteria->addAggregation($aggregation);
        }

        foreach ($filters as $filter) {
            if ($filter->isFiltered()) {
                $criteria->addPostFilter($filter->getFilter());
            }
        }

        $criteria->addExtension('filters', $filters);
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        $this->addCurrentFilters($result);

        foreach ($this->factories as $factory) {
            $factory->process($request, $result, $context);
        }
    }

    private function addCurrentFilters(ProductListingResult $result): void
    {
        $filters = $result->getCriteria()->getExtension('filters');
        if (!$filters instanceof FilterCollection) {
            return;
        }

        foreach ($filters as $filter) {
            $result->addCurrentFilter($filter->getName(), $filter->getValues());
        }
    }

    private function getFilters(Request $request, SalesChannelContext $context): FilterCollection
    {
        $filters = new FilterCollection();

        foreach ($this->factories as $factory) {
            $filter = $factory->create($request, $context);

            if ($filter !== null) {
                $filters->add($filter);
            }
        }

        $event = new ProductListingCollectFilterEvent($request, $filters, $context);
        $this->dispatcher->dispatch($event);

        return $filters;
    }

    /**
     * @return array<Aggregation>
     */
    private function getAggregations(Request $request, FilterCollection $filters): array
    {
        $aggregations = [];

        if (RequestParamHelper::get($request, 'reduce-aggregations') === null) {
            foreach ($filters as $filter) {
                $aggregations[] = $filter->getAggregations();
            }

            return array_merge(...$aggregations);
        }

        $base = $filters->filtered();

        foreach ($filters as $filter) {
            $excluded = $filter->exclude() ? $base->blacklist($filter->getName()) : $base;

            foreach ($filter->getAggregations() as $aggregation) {
                if ($aggregation instanceof FilterAggregation) {
                    $aggregation->addFilters($excluded->getFilters());

                    $aggregations[] = $aggregation;

                    continue;
                }

                $aggregation = new FilterAggregation(
                    $aggregation->getName(),
                    $aggregation,
                    $excluded->getFilters()
                );

                $aggregations[] = $aggregation;
            }
        }

        return $aggregations;
    }
}
