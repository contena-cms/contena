<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Acl;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
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

        $context = new Context(
            $source,
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $missing = $this->validator->validate(BlogDefinition::ENTITY_NAME, $criteria, $context);

        if ($pass) {
            static::assertCount(0, $missing);

            return;
        }

        static::assertNotCount(0, $missing);
    }

    /**
     * @return iterable<string, array<int, array<int, string>|bool|Criteria>>
     */
    public static function criteriaProvider(): iterable
    {
        yield 'Has read permission for root entity' => [
            ['blog:read'],
            new Criteria(),
            true,
        ];
        yield 'Missing permissions for root entity' => [
            [],
            new Criteria(),
            false,
        ];
        yield 'Has permissions for association' => [
            ['blog:read', 'blog_manufacturer:read'],
            (new Criteria())->addAssociation('manufacturer'),
            true,
        ];
        yield 'Missing permissions for association' => [
            ['blog:read'],
            (new Criteria())->addAssociation('manufacturer'),
            false,
        ];
        yield 'Has permissions for association but not for root' => [
            ['blog_manufacturer:read'],
            (new Criteria())->addAssociation('manufacturer'),
            false,
        ];
        yield 'Has permissions for nested association' => [
            ['blog:read', 'category:read', 'media:read'],
            (new Criteria())->addAssociation('categories.media'),
            true,
        ];
        yield 'Missing permissions for nested association' => [
            ['blog:read', 'category:read'],
            (new Criteria())->addAssociation('categories.media'),
            false,
        ];
        yield 'Has permissions for filter' => [
            ['blog:read', 'category:read'],
            (new Criteria())
                ->addFilter(new EqualsFilter('categories.active', true)),
            true,
        ];
        yield 'Missing permissions for filter' => [
            ['blog:read'],
            (new Criteria())
                ->addFilter(new EqualsFilter('categories.active', true)),
            false,
        ];
        yield 'Has permissions for nested filter' => [
            ['blog:read', 'category:read', 'media:read'],
            (new Criteria())
                ->addFilter(new EqualsFilter('categories.media.private', true)),
            true,
        ];
        yield 'Missing permissions for nested filter' => [
            ['blog:read'],
            (new Criteria())
                ->addFilter(new EqualsFilter('categories.media.private', true)),
            false,
        ];
        yield 'Has permissions for post filter' => [
            ['blog:read', 'category:read'],
            (new Criteria())
                ->addPostFilter(new EqualsFilter('categories.active', true)),
            true,
        ];
        yield 'Missing permissions for post filter' => [
            ['blog:read'],
            (new Criteria())
                ->addPostFilter(new EqualsFilter('categories.active', true)),
            false,
        ];
        yield 'Has permissions for nested post filter' => [
            ['blog:read', 'category:read', 'media:read'],
            (new Criteria())
                ->addPostFilter(new EqualsFilter('categories.media.private', true)),
            true,
        ];
        yield 'Missing permissions for nested post filter' => [
            ['blog:read'],
            (new Criteria())
                ->addPostFilter(new EqualsFilter('categories.media.private', true)),
            false,
        ];
        yield 'Has permissions for sorting' => [
            ['blog:read', 'category:read'],
            (new Criteria())
                ->addSorting(new FieldSorting('categories.active')),
            true,
        ];
        yield 'Missing permissions for sorting' => [
            ['blog:read'],
            (new Criteria())
                ->addSorting(new FieldSorting('categories.active')),
            false,
        ];
        yield 'Has permissions for nested sorting' => [
            ['blog:read', 'category:read', 'media:read'],
            (new Criteria())
                ->addSorting(new FieldSorting('categories.media.private')),
            true,
        ];
        yield 'Missing permissions for nested sorting' => [
            ['blog:read'],
            (new Criteria())
                ->addSorting(new FieldSorting('categories.media.private')),
            false,
        ];
        yield 'Has permissions for query' => [
            ['blog:read', 'category:read'],
            (new Criteria())
                ->addQuery(new ScoreQuery(new EqualsFilter('categories.active', true), 100)),
            true,
        ];
        yield 'Missing permissions for query' => [
            ['blog:read'],
            (new Criteria())
                ->addQuery(new ScoreQuery(new EqualsFilter('categories.active', true), 100)),
            false,
        ];
        yield 'Has permissions for nested query' => [
            ['blog:read', 'category:read', 'media:read'],
            (new Criteria())
                ->addQuery(new ScoreQuery(new EqualsFilter('categories.media.private', true), 100)),
            true,
        ];
        yield 'Missing permissions for nested query' => [
            ['blog:read'],
            (new Criteria())
                ->addQuery(new ScoreQuery(new EqualsFilter('categories.media.private', true), 100)),
            false,
        ];
        yield 'Has permissions for grouping' => [
            ['blog:read', 'category:read'],
            (new Criteria())
                ->addGroupField(new FieldGrouping('categories.active')),
            true,
        ];
        yield 'Missing permissions for grouping' => [
            ['blog:read'],
            (new Criteria())
                ->addGroupField(new FieldGrouping('categories.active')),
            false,
        ];
        yield 'Has permissions for nested grouping' => [
            ['blog:read', 'category:read', 'media:read'],
            (new Criteria())
                ->addGroupField(new FieldGrouping('categories.media.private')),
            true,
        ];
        yield 'Missing permissions for nested grouping' => [
            ['blog:read'],
            (new Criteria())
                ->addGroupField(new FieldGrouping('categories.media.private')),
            false,
        ];
        yield 'Has permissions for aggregation' => [
            ['blog:read', 'category:read'],
            (new Criteria())
                ->addAggregation(new CountAggregation('count-agg', 'categories.active')),
            true,
        ];
        yield 'Missing permissions for aggregation' => [
            ['blog:read'],
            (new Criteria())
                ->addAggregation(new CountAggregation('count-agg', 'categories.active')),
            false,
        ];
        yield 'Has permissions for nested aggregation' => [
            ['blog:read', 'category:read', 'media:read'],
            (new Criteria())
                ->addAggregation(new CountAggregation('count-agg', 'categories.media.private')),
            true,
        ];
        yield 'Missing permissions for nested aggregation' => [
            ['blog:read'],
            (new Criteria())
                ->addAggregation(new CountAggregation('count-agg', 'categories.media.private')),
            false,
        ];
    }
}
