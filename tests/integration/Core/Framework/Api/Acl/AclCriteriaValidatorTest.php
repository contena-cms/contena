<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Acl;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class AclCriteriaValidatorTest extends TestCase
{
    use KernelTestBehaviour;

    private AclCriteriaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = static::getContainer()->get(AclCriteriaValidator::class);
    }

    /**
     * @param array<int, string> $privileges
     */
    #[DataProvider('criteriaProvider')]
    public function testValidateCriteria(array $privileges, Criteria $criteria, bool $pass): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($privileges);

        $context = Context::createDefaultContext($source);

        $missing = $this->validator->validate(MediaDefinition::ENTITY_NAME, $criteria, $context);

        if ($pass) {
            static::assertEmpty($missing);

            return;
        }

        static::assertNotEmpty($missing);
    }

    /**
     * @return iterable<string, array<int, array<int, string>|bool|Criteria>>
     */
    public static function criteriaProvider(): iterable
    {
        yield 'Has read permission for root entity' => [
            ['media:read'],
            new Criteria(),
            true,
        ];
        yield 'Missing permissions for root entity' => [
            [],
            new Criteria(),
            false,
        ];
        yield 'Has permissions for association' => [
            ['media:read', 'media_folder:read'],
            new Criteria()->addAssociation('mediaFolder'),
            true,
        ];
        yield 'Missing permissions for association' => [
            ['media:read'],
            new Criteria()->addAssociation('mediaFolder'),
            false,
        ];
        yield 'Has permissions for association but not for root' => [
            ['media_folder:read'],
            new Criteria()->addAssociation('mediaFolder'),
            false,
        ];
        yield 'Has permissions for nested association' => [
            ['media:read', 'media_folder:read', 'media_folder_configuration:read'],
            new Criteria()->addAssociation('mediaFolder.configuration'),
            true,
        ];
        yield 'Missing permissions for nested association' => [
            ['media:read', 'media_folder:read'],
            new Criteria()->addAssociation('mediaFolder.configuration'),
            false,
        ];
        yield 'Has permissions for filter' => [
            ['media:read', 'media_folder:read'],
            new Criteria()
                ->addFilter(new EqualsFilter('mediaFolder.name', 'Documents')),
            true,
        ];
        yield 'Missing permissions for filter' => [
            ['media:read'],
            new Criteria()
                ->addFilter(new EqualsFilter('mediaFolder.name', 'Documents')),
            false,
        ];
        yield 'Has permissions for nested filter' => [
            ['media:read', 'media_folder:read', 'media_folder_configuration:read'],
            new Criteria()
                ->addFilter(new EqualsFilter('mediaFolder.configuration.private', true)),
            true,
        ];
        yield 'Missing permissions for nested filter' => [
            ['media:read'],
            new Criteria()
                ->addFilter(new EqualsFilter('mediaFolder.configuration.private', true)),
            false,
        ];
        yield 'Has permissions for post filter' => [
            ['media:read', 'media_folder:read'],
            new Criteria()
                ->addPostFilter(new EqualsFilter('mediaFolder.name', 'Documents')),
            true,
        ];
        yield 'Missing permissions for post filter' => [
            ['media:read'],
            new Criteria()
                ->addPostFilter(new EqualsFilter('mediaFolder.name', 'Documents')),
            false,
        ];
        yield 'Has permissions for nested post filter' => [
            ['media:read', 'media_folder:read', 'media_folder_configuration:read'],
            new Criteria()
                ->addPostFilter(new EqualsFilter('mediaFolder.configuration.private', true)),
            true,
        ];
        yield 'Missing permissions for nested post filter' => [
            ['media:read'],
            new Criteria()
                ->addPostFilter(new EqualsFilter('mediaFolder.configuration.private', true)),
            false,
        ];
        yield 'Has permissions for sorting' => [
            ['media:read', 'media_folder:read'],
            new Criteria()
                ->addSorting(new FieldSorting('mediaFolder.name')),
            true,
        ];
        yield 'Missing permissions for sorting' => [
            ['media:read'],
            new Criteria()
                ->addSorting(new FieldSorting('mediaFolder.name')),
            false,
        ];
        yield 'Has permissions for nested sorting' => [
            ['media:read', 'media_folder:read', 'media_folder_configuration:read'],
            new Criteria()
                ->addSorting(new FieldSorting('mediaFolder.configuration.private')),
            true,
        ];
        yield 'Missing permissions for nested sorting' => [
            ['media:read'],
            new Criteria()
                ->addSorting(new FieldSorting('mediaFolder.configuration.private')),
            false,
        ];
        yield 'Has permissions for query' => [
            ['media:read', 'media_folder:read'],
            new Criteria()
                ->addQuery(new ScoreQuery(new EqualsFilter('mediaFolder.name', 'Documents'), 100)),
            true,
        ];
        yield 'Missing permissions for query' => [
            ['media:read'],
            new Criteria()
                ->addQuery(new ScoreQuery(new EqualsFilter('mediaFolder.name', 'Documents'), 100)),
            false,
        ];
        yield 'Has permissions for nested query' => [
            ['media:read', 'media_folder:read', 'media_folder_configuration:read'],
            new Criteria()
                ->addQuery(new ScoreQuery(new EqualsFilter('mediaFolder.configuration.private', true), 100)),
            true,
        ];
        yield 'Missing permissions for nested query' => [
            ['media:read'],
            new Criteria()
                ->addQuery(new ScoreQuery(new EqualsFilter('mediaFolder.configuration.private', true), 100)),
            false,
        ];
        yield 'Has permissions for grouping' => [
            ['media:read', 'media_folder:read'],
            new Criteria()
                ->addGroupField(new FieldGrouping('mediaFolder.name')),
            true,
        ];
        yield 'Missing permissions for grouping' => [
            ['media:read'],
            new Criteria()
                ->addGroupField(new FieldGrouping('mediaFolder.name')),
            false,
        ];
        yield 'Has permissions for nested grouping' => [
            ['media:read', 'media_folder:read', 'media_folder_configuration:read'],
            new Criteria()
                ->addGroupField(new FieldGrouping('mediaFolder.configuration.private')),
            true,
        ];
        yield 'Missing permissions for nested grouping' => [
            ['media:read'],
            new Criteria()
                ->addGroupField(new FieldGrouping('mediaFolder.configuration.private')),
            false,
        ];
        yield 'Has permissions for aggregation' => [
            ['media:read', 'media_folder:read'],
            new Criteria()
                ->addAggregation(new CountAggregation('count-agg', 'mediaFolder.name')),
            true,
        ];
        yield 'Missing permissions for aggregation' => [
            ['media:read'],
            new Criteria()
                ->addAggregation(new CountAggregation('count-agg', 'mediaFolder.name')),
            false,
        ];
        yield 'Has permissions for nested aggregation' => [
            ['media:read', 'media_folder:read', 'media_folder_configuration:read'],
            new Criteria()
                ->addAggregation(new CountAggregation('count-agg', 'mediaFolder.configuration.private')),
            true,
        ];
        yield 'Missing permissions for nested aggregation' => [
            ['media:read'],
            new Criteria()
                ->addAggregation(new CountAggregation('count-agg', 'mediaFolder.configuration.private')),
            false,
        ];
    }
}
