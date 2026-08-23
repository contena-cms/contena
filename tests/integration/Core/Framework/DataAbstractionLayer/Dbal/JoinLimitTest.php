<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Commercial\Content\Product\ProductDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * MySQL and MariaDB can only reference 61 tables in a single join. A criteria that
 * traverses many associations must spill filter-only associations into subqueries.
 *
 * @internal
 */
final class JoinLimitTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const MAX_TABLES_PER_JOIN = 61;

    public function testCriteriaWithManyAssociationsStaysBelowTheJoinLimit(): void
    {
        $criteria = new Criteria();
        foreach ($this->manyAccessors() as $accessor) {
            $criteria->addFilter(new EqualsFilter($accessor, Uuid::randomHex()));
        }

        static::assertLessThanOrEqual(self::MAX_TABLES_PER_JOIN, $this->outerJoinSlots($criteria));
    }

    public function testSortedAssociationKeepsItsJoin(): void
    {
        $criteria = new Criteria();
        foreach ($this->manyAccessors() as $accessor) {
            $criteria->addFilter(new EqualsFilter($accessor, Uuid::randomHex()));
        }
        $criteria->addSorting(new FieldSorting('product.prices.quantityStart'));

        $sql = $this->buildSql($criteria);

        static::assertStringContainsString('`product.prices`', $sql);
        static::assertLessThanOrEqual(self::MAX_TABLES_PER_JOIN, $this->outerJoinSlots($criteria));
    }

    /**
     * @return list<string>
     */
    private function manyAccessors(): array
    {
        $leaves = array_map(
            static fn (string $field): string => $field . '.id',
            ['prices', 'media', 'configuratorSettings', 'visibilities', 'seoUrls', 'options', 'properties', 'tags', 'streams', 'tax', 'manufacturer', 'unit', 'deliveryTime', 'featureSet', 'cover', 'openGraphMedia'],
        );

        $accessors = [];
        foreach (['parent.', 'canonicalProduct.', 'parent.parent.', 'canonicalProduct.parent.', 'parent.parent.parent.'] as $prefix) {
            foreach ($leaves as $leaf) {
                $accessors[] = $prefix . $leaf;
            }
        }

        return $accessors;
    }

    private function buildSql(Criteria $criteria): string
    {
        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $definition = static::getContainer()->get(ProductDefinition::class);
        $connection = static::getContainer()->get(Connection::class);

        return Context::createDefaultContext()->enableInheritance(
            function (Context $context) use ($builder, $definition, $connection, $criteria): string {
                $query = new QueryBuilder($connection);
                $query->select('product.id');

                return $builder->build($query, $definition, clone $criteria, $context)->getSQL();
            }
        );
    }

    private function outerJoinSlots(Criteria $criteria): int
    {
        return self::countOuterSlots($this->buildSql($criteria));
    }

    private static function countOuterSlots(string $sql): int
    {
        $depth = 0;
        $slots = 0;
        $length = \strlen($sql);
        for ($i = 0; $i < $length; ++$i) {
            $char = $sql[$i];
            if ($char === '(') {
                ++$depth;
                continue;
            }
            if ($char === ')') {
                --$depth;
                continue;
            }
            if ($depth === 0 && preg_match('/^(FROM|JOIN)\\s/i', substr($sql, $i, 5)) === 1) {
                ++$slots;
            }
        }

        return $slots;
    }
}
