<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Parser;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;

/**
 * @internal
 */
class AggregationParser
{

    /**
     * @param array<Aggregation> $aggregations
     *
     * @return array<array<string, mixed>>
     */
    public function toArray(array $aggregations): array
    {
        $data = [];

        foreach ($aggregations as $aggregation) {
            $data[] = $this->aggregationToArray($aggregation);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregationToArray(Aggregation $aggregation): array
    {
        if ($aggregation instanceof AvgAggregation) {
            return [
                'name' => $aggregation->getName(),
                'type' => 'avg',
                'field' => $aggregation->getField(),
            ];
        }
        if ($aggregation instanceof MaxAggregation) {
            return [
                'name' => $aggregation->getName(),
                'type' => 'max',
                'field' => $aggregation->getField(),
            ];
        }
        if ($aggregation instanceof MinAggregation) {
            return [
                'name' => $aggregation->getName(),
                'type' => 'min',
                'field' => $aggregation->getField(),
            ];
        }
        if ($aggregation instanceof StatsAggregation) {
            return [
                'name' => $aggregation->getName(),
                'type' => 'stats',
                'field' => $aggregation->getField(),
            ];
        }
        if ($aggregation instanceof SumAggregation) {
            return [
                'name' => $aggregation->getName(),
                'type' => 'sum',
                'field' => $aggregation->getField(),
            ];
        }
        if ($aggregation instanceof CountAggregation) {
            return [
                'name' => $aggregation->getName(),
                'type' => 'count',
                'field' => $aggregation->getField(),
            ];
        }
        if ($aggregation instanceof EntityAggregation) {
            return [
                'name' => $aggregation->getName(),
                'type' => 'entity',
                'field' => $aggregation->getField(),
                'definition' => $aggregation->getEntity(),
            ];
        }
        if ($aggregation instanceof FilterAggregation) {
            $filters = [];
            foreach ($aggregation->getFilter() as $filter) {
                $filters[] = QueryStringParser::toArray($filter);
            }

            $aggregationArray = [
                'name' => $aggregation->getName(),
                'type' => 'filter',
                'filter' => $filters,
            ];

            $nestedAggregation = $aggregation->getAggregation();
            if ($nestedAggregation) {
                $aggregationArray['aggregation'] = $this->aggregationToArray($nestedAggregation);
            }

            return $aggregationArray;
        }
        if ($aggregation instanceof DateHistogramAggregation) {
            $data = [
                'name' => $aggregation->getName(),
                'type' => 'histogram',
                'interval' => $aggregation->getInterval(),
                'format' => $aggregation->getFormat(),
                'field' => $aggregation->getField(),
                'timeZone' => $aggregation->getTimeZone(),
            ];

            if ($aggregation->getSorting()) {
                $data['sort'] = [
                    'order' => $aggregation->getSorting()->getDirection(),
                    'naturalSorting' => $aggregation->getSorting()->getNaturalSorting(),
                    'field' => $aggregation->getSorting()->getField(),
                ];
            }

            if ($aggregation->getAggregation()) {
                $data['aggregation'] = $this->aggregationToArray($aggregation->getAggregation());
            }

            return $data;
        }

        if ($aggregation instanceof TermsAggregation) {
            $data = [
                'name' => $aggregation->getName(),
                'type' => 'terms',
                'field' => $aggregation->getField(),
            ];

            if ($aggregation->getSorting()) {
                $data['sort'] = [
                    'order' => $aggregation->getSorting()->getDirection(),
                    'naturalSorting' => $aggregation->getSorting()->getNaturalSorting(),
                    'field' => $aggregation->getSorting()->getField(),
                ];
            }

            if ($aggregation->getAggregation()) {
                $data['aggregation'] = $this->aggregationToArray($aggregation->getAggregation());
            }

            return $data;
        }

        throw DataAbstractionLayerException::invalidAggregationQuery(\sprintf('The aggregation of type "%s" is not supported.', $aggregation::class));
    }


    private static function buildFieldName(EntityDefinition $definition, string $fieldName): string
    {
        $prefix = $definition->getEntityName() . '.';
        $fieldName = AssociationIdPathNormalizer::normalize($definition, $fieldName);

        if (!str_contains($fieldName, $prefix)) {
            return $prefix . $fieldName;
        }

        return $fieldName;
    }
}
