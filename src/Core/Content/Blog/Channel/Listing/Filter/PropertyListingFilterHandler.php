<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing\Filter;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\Filter;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

class PropertyListingFilterHandler extends AbstractListingFilterHandler
{
    final public const FILTER_ENABLED_REQUEST_PARAM = 'property-filter';

    final public const PROPERTY_GROUP_IDS_REQUEST_PARAM = 'property-whitelist';

    /**
     * @param EntityRepository<PropertyGroupCollection> $groupRepository
     * @param EntityRepository<PropertyGroupOptionCollection> $optionRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $groupRepository,
        private readonly EntityRepository $optionRepository,
        private readonly Connection $connection
    ) {
    }

    public function getDecorated(): AbstractListingFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(Request $request, ChannelContext $context): ?Filter
    {
        $groupIds = $request->request->all(self::PROPERTY_GROUP_IDS_REQUEST_PARAM);

        if (!$request->request->get(self::FILTER_ENABLED_REQUEST_PARAM, true) && $groupIds === []) {
            return null;
        }

        return $this->getPropertyFilter($request, $groupIds);
    }

    public function process(Request $request, BlogListingResult $result, ChannelContext $context): void
    {
        $ids = $this->collectOptionIds($result);

        if ($ids === []) {
            return;
        }

        $chunkIds = array_chunk($ids, 1000);

        $optionCriteria = new Criteria();
        $optionCriteria->addAssociation('media');
        $optionCriteria->setTitle('blog-listing::property-filter');

        $options = [];
        $groupIds = [];

        foreach ($chunkIds as $chunk) {
            $cloned = clone $optionCriteria;
            $cloned->setIds($chunk);

            $entities = $this->optionRepository->search($cloned, $context->getContext())->getEntities();

            $options = array_merge($options, $entities->getElements());

            foreach ($entities as $option) {
                if (!isset($groupIds[$option->getGroupId()])) {
                    $groupIds[$option->getGroupId()] = true;
                }
            }
        }

        $groupCriteria = new Criteria();
        $groupCriteria->setTitle('blog-listing::property-group-filter');
        $groupCriteria->addFilter(new EqualsFilter('filterable', true));

        $groups = new PropertyGroupCollection();

        $chunkIds = array_chunk(array_keys($groupIds), 1000);

        foreach ($chunkIds as $chunk) {
            $cloned = clone $groupCriteria;

            $cloned->setIds($chunk);

            $groupResult = $this->groupRepository->search($cloned, $context->getContext());

            $groups->fill($groupResult->getEntities()->getElements());
        }

        foreach ($groups as $group) {
            $group->setOptions(new PropertyGroupOptionCollection());
        }

        foreach ($options as $option) {
            $groups->get($option->getGroupId())?->getOptions()?->add($option);
        }

        $groups->sortByPositions();
        $groups->sortByConfig($context->getLanguageInfo()->localeCode);

        $aggregations = $result->getAggregations();

        // remove id results to prevent wrong usages
        $aggregations->remove('properties');
        $aggregations->remove('configurators');
        $aggregations->remove('options');

        $aggregations->add(new EntityResult('properties', $groups));
    }

    /**
     * @param array<string>|null $groupIds
     */
    private function getPropertyFilter(Request $request, ?array $groupIds = null): Filter
    {
        $ids = $this->getPropertyIds($request);

        $propertyAggregation = new TermsAggregation('properties', 'blog.properties.id');

        $optionAggregation = new TermsAggregation('options', 'blog.options.id');

        if ($groupIds) {
            $propertyAggregation = new FilterAggregation(
                'properties-filter',
                $propertyAggregation,
                [new EqualsAnyFilter('blog.properties.groupId', $groupIds)]
            );

            $optionAggregation = new FilterAggregation(
                'options-filter',
                $optionAggregation,
                [new EqualsAnyFilter('blog.options.groupId', $groupIds)]
            );
        }

        $aggregations = [$propertyAggregation, $optionAggregation];

        if ($ids === []) {
            return new Filter('properties', false, $aggregations, new AndFilter([]), [], false);
        }

        $grouped = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(property_group_id)) as property_group_id, LOWER(HEX(id)) as id
             FROM property_group_option
             WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $grouped = FetchModeHelper::group($grouped, static fn ($row): string => (string) $row['id']);

        $filters = [];
        foreach ($grouped as $options) {
            $filters[] = new OrFilter([
                new EqualsAnyFilter('blog.optionIds', $options),
                new EqualsAnyFilter('blog.propertyIds', $options),
            ]);
        }

        return new Filter('properties', true, $aggregations, new AndFilter($filters), $ids, false);
    }

    /**
     * @return array<int, non-falsy-string>
     */
    private function collectOptionIds(BlogListingResult $result): array
    {
        $aggregations = $result->getAggregations();

        $properties = $aggregations->get('properties');
        \assert($properties instanceof TermsResult || $properties === null);

        $options = $aggregations->get('options');
        \assert($options instanceof TermsResult || $options === null);

        $options = $options ? $options->getKeys() : [];
        $properties = $properties ? $properties->getKeys() : [];

        return array_unique(array_filter([...$options, ...$properties]));
    }

    /**
     * @return list<string>
     */
    private function getPropertyIds(Request $request): array
    {
        $ids = $request->query->get('properties', '');
        if ($request->isMethod(Request::METHOD_POST)) {
            $ids = $request->request->get('properties', '');
        }

        if (\is_string($ids)) {
            $ids = explode('|', $ids);
        }

        $return = [];
        foreach ((array) $ids as $id) {
            if (!\is_string($id)) {
                continue;
            }
            if (Uuid::isValid($id)) {
                $return[] = $id;
            }
        }

        return $return;
    }
}
