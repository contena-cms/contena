<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Parser;

use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Symfony\Component\Clock\Clock;

/**
 * @internal
 *
 * @phpstan-type EqualsFilterType array{type: 'equals', field: string, value: mixed}
 * @phpstan-type NotFilterType array{type: 'not', queries: array<mixed>, operator: string}
 * @phpstan-type MultiFilterType array{type: 'multi', queries: array<mixed>, operator: string}
 * @phpstan-type ContainsFilterType array{type: 'contains', field: string, value: mixed}
 * @phpstan-type PrefixFilterType array{type: 'prefix', field: string, value: mixed}
 * @phpstan-type SuffixFilterType array{type: 'suffix', field: string, value: mixed}
 * @phpstan-type RangeFilterType array{type: 'range'|'until'|'since', field: string, value?: mixed, parameters: array<string, mixed>}
 * @phpstan-type EqualsAnyFilterType array{type: 'equalsAny', field: string, value: mixed}
 * @phpstan-type Query array{
 *     type: string,
 *     field?: string,
 *     value?: mixed,
 *     parameters?: array{
 *         operator: RangeFilter::*
 *     },
 *     queries?: list<array{
 *         type: string,
 *         field?: string,
 *         value?: mixed
 *     }>|null
 * }
 */
class QueryStringParser
{

    /**
     * @return EqualsFilterType|NotFilterType|MultiFilterType|ContainsFilterType|PrefixFilterType|SuffixFilterType|RangeFilterType|EqualsAnyFilterType
     */
    public static function toArray(Filter $query): array
    {
        return match (true) {
            $query instanceof EqualsFilter => [
                'type' => 'equals',
                'field' => $query->getField(),
                'value' => $query->getValue(),
            ],
            $query instanceof NotFilter => [
                'type' => 'not',
                'queries' => array_map(static fn (Filter $nested) => self::toArray($nested), $query->getQueries()),
                'operator' => $query->getOperator(),
            ],
            $query instanceof MultiFilter => [
                'type' => 'multi',
                'queries' => array_map(static fn (Filter $nested) => self::toArray($nested), $query->getQueries()),
                'operator' => $query->getOperator(),
            ],
            $query instanceof ContainsFilter => [
                'type' => 'contains',
                'field' => $query->getField(),
                'value' => $query->getValue(),
            ],
            $query instanceof PrefixFilter => [
                'type' => 'prefix',
                'field' => $query->getField(),
                'value' => $query->getValue(),
            ],
            $query instanceof SuffixFilter => [
                'type' => 'suffix',
                'field' => $query->getField(),
                'value' => $query->getValue(),
            ],
            $query instanceof RangeFilter => [
                'type' => 'range',
                'field' => $query->getField(),
                'parameters' => $query->getParameters(),
            ],
            $query instanceof EqualsAnyFilter => [
                'type' => 'equalsAny',
                'field' => $query->getField(),
                'value' => implode('|', $query->getValue()),
            ],
            default => throw DataAbstractionLayerException::invalidFilterQuery(\sprintf('Unsupported filter type %s', $query::class)),
        };
    }


    /**
     * @param array<string, mixed> $query
     */
    private static function getFilterByRelativeTime(string $fieldName, string $type, array $query, string $path): MultiFilter
    {
        if (!isset($query['field']) || !\is_string($query['field']) || $query['field'] === '') {
            throw DataAbstractionLayerException::invalidFilterQuery(\sprintf('Parameter "field" for %s filter is missing.', $type), $path . '/field');
        }

        $queryValue = $query['value'] ?? '';
        if (!\is_string($queryValue) || $queryValue === '') {
            throw DataAbstractionLayerException::invalidFilterQuery(\sprintf('Parameter "value" for %s filter is missing.', $type), $path . '/value');
        }

        $operator = $query['parameters']['operator'] ?? '';
        if (!\is_string($operator) || $operator === '') {
            throw DataAbstractionLayerException::invalidFilterQuery(\sprintf('Parameter "parameter.operator" for %s filter is missing.', $type), $path . '/parameters/operator');
        }
        $operator = mb_strtolower($operator);
        $validOperators = [RangeFilter::LTE, RangeFilter::GTE, RangeFilter::LT, RangeFilter::GT, 'eq', 'neq'];
        if (!\in_array($operator, $validOperators, true)) {
            throw DataAbstractionLayerException::invalidFilterQuery(
                \sprintf('Parameter "parameter.operator" for %s filter must be one of: %s', $type, implode(', ', $validOperators)),
                $path . '/parameters/operator'
            );
        }

        $now = Clock::get()->now();

        try {
            $dateInterval = new \DateInterval($queryValue);
        } catch (\Exception) {
            throw DataAbstractionLayerException::invalidFilterQuery(
                \sprintf('Parameter "value" for %s filter must be a valid date interval, got "%s".', $type, $queryValue),
                $path . '/value'
            );
        }

        if ($type === 'since') {
            $dateInterval->invert = 1;
        }
        $thresholdDate = $now->add($dateInterval);

        // if we're matching for time until, date must be in the future
        // if we're matching for time since, date must be in the past
        if ($type === 'until') {
            $secondaryFilter = new RangeFilter(
                $fieldName,
                [RangeFilter::GT => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
            );
        } else {
            $secondaryFilter = new RangeFilter(
                $fieldName,
                [RangeFilter::LT => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
            );
            // for time since we may need to negate the primary filter operator
            $operator = self::negateOperator($operator);
        }

        $primaryFilter = match ($operator) {
            'eq' => new RangeFilter($fieldName, self::getDayRangeParameters($thresholdDate)),
            'neq' => new NotFilter(
                NotFilter::CONNECTION_AND,
                [new RangeFilter($fieldName, self::getDayRangeParameters($thresholdDate))]
            ),
            default => new RangeFilter(
                $fieldName,
                [$operator => $thresholdDate->format(Defaults::STORAGE_DATE_FORMAT)]
            ),
        };

        return new MultiFilter(MultiFilter::CONNECTION_AND, [$primaryFilter, $secondaryFilter]);
    }

    /**
     * @param RangeFilter::*|"eq"|"neq" $operator
     *
     * @return RangeFilter::*|"eq"|"neq"
     */
    private static function negateOperator(string $operator): string
    {
        return match ($operator) {
            RangeFilter::LT => RangeFilter::GT,
            RangeFilter::GT => RangeFilter::LT,
            RangeFilter::LTE => RangeFilter::GTE,
            RangeFilter::GTE => RangeFilter::LTE,
            default => $operator,
        };
    }

    private static function buildFieldName(EntityDefinition $definition, string $fieldName): string
    {
        $prefix = $definition->getEntityName() . '.';
        $normalized = AssociationIdPathNormalizer::normalize($definition, $fieldName);

        return str_starts_with($normalized, $prefix) ? $normalized : $prefix . $normalized;
    }

    /**
     * @return array{gte: string, lte: string}
     */
    private static function getDayRangeParameters(\DateTimeImmutable $thresholdDate): array
    {
        return [
            RangeFilter::GTE => $thresholdDate->setTime(0, 0, 0)->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            RangeFilter::LTE => $thresholdDate->setTime(23, 59, 59)->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }
}
