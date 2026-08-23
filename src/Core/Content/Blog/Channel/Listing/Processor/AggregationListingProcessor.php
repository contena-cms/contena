<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing\Processor;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\Filter\AbstractListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\FilterCollection;
use Contena\Core\Content\Blog\Events\BlogListingCollectFilterEvent;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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

    public function prepare(Request $request, Criteria $criteria, ChannelContext $context): void
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

    public function process(Request $request, BlogListingResult $result, ChannelContext $context): void
    {
        $this->addCurrentFilters($result);

        foreach ($this->factories as $factory) {
            $factory->process($request, $result, $context);
        }
    }

    private function addCurrentFilters(BlogListingResult $result): void
    {
        $filters = $result->getCriteria()->getExtension('filters');
        if (!$filters instanceof FilterCollection) {
            return;
        }

        foreach ($filters as $filter) {
            $result->addCurrentFilter($filter->getName(), $filter->getValues());
        }
    }

    private function getFilters(Request $request, ChannelContext $context): FilterCollection
    {
        $filters = new FilterCollection();

        foreach ($this->factories as $factory) {
            $filter = $factory->create($request, $context);

            if ($filter !== null) {
                $filters->add($filter);
            }
        }

        $event = new BlogListingCollectFilterEvent($request, $filters, $context);
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
                $aggregations = array_merge($aggregations, $filter->getAggregations());
            }

            return $aggregations;
        }

        foreach ($filters as $filter) {
            $excluded = $filters->filtered();

            if ($filter->exclude()) {
                $excluded = $excluded->blacklist($filter->getName());
            }

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
