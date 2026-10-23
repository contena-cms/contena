<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Search;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NorFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 *
 * @see MultiJoinFilterLimitationTest for edge cases and limitations of multi join filters
 */
class JoinFilterTest extends TestCase
{
    use KernelTestBehaviour;

    private static IdsCollection $ids;

    private static bool $dataInserted = false;

    protected function setUp(): void
    {
        // Insert in setUp() not #[BeforeClass]: the repository call triggers a deprecation whose handler requires a TestCase on the stack.
        if (self::$dataInserted) {
            return;
        }

        self::insertTestData();
        self::$dataInserted = true;
    }

    #[BeforeClass]
    public static function startTransactionBefore(): void
    {
        self::$ids = new IdsCollection();
        KernelLifecycleManager::getKernel()->getContainer()->get(Connection::class)->beginTransaction();
    }

    #[AfterClass]
    public static function stopTransactionAfter(): void
    {
        KernelLifecycleManager::getKernel()->getContainer()->get(Connection::class)->rollBack();
        self::$dataInserted = false;
    }

    public function testOneToOne(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new NandFilter([new EqualsFilter('avatarUsers.id', null)])
        );

        $media = static::getContainer()->get('media.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertCount(1, $media->getIds());
        static::assertContains(self::$ids->get('with-avatar'), $media->getIds());
        static::assertNotContains(self::$ids->get('without-avatar'), $media->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('avatarUsers.id', null));

        $media = static::getContainer()->get('media.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertTrue(\count($media->getIds()) > 0);
        static::assertContains(self::$ids->get('without-avatar'), $media->getIds());
        static::assertNotContains(self::$ids->get('with-avatar'), $media->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(
            new OrFilter([
                new EqualsFilter('avatarUsers.id', null),
                new NandFilter([new EqualsFilter('avatarUsers.id', Uuid::randomHex())]),
            ])
        );

        $media = static::getContainer()->get('media.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertTrue(\count($media->getIds()) > 0);
        static::assertContains(self::$ids->get('with-avatar'), $media->getIds());
        static::assertContains(self::$ids->get('without-avatar'), $media->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(
            new NandFilter([new EqualsFilter('avatarUsers.id', Uuid::randomHex())])
        );

        $media = static::getContainer()->get('media.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertTrue(\count($media->getIds()) > 0);
        static::assertContains(self::$ids->get('with-avatar'), $media->getIds());
        static::assertContains(self::$ids->get('without-avatar'), $media->getIds());
    }

    public function testAggregationWithFilter(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('properties.id', self::$ids->getList(['red']))
        );

        $criteria->addAggregation(
            new TermsAggregation('filters', 'properties.id')
        );

        $criteria->setLimit(0);

        $blogs = static::getContainer()->get('blog.repository')
            ->search($criteria, Context::createDefaultContext());

        $aggregation = $blogs->getAggregations()->get('filters');

        static::assertInstanceOf(TermsResult::class, $aggregation);

        static::assertContains(self::$ids->get('red'), $aggregation->getKeys());
        static::assertContains(self::$ids->get('yellow'), $aggregation->getKeys());
        static::assertContains(self::$ids->get('XL'), $aggregation->getKeys());
        static::assertContains(self::$ids->get('L'), $aggregation->getKeys());
    }

    public function testAggregationWithNegatedFilter(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new NandFilter([
                new EqualsAnyFilter('properties.id', self::$ids->getList(['XL'])),
            ])
        );

        $criteria->addAggregation(
            new TermsAggregation('filters', 'properties.id')
        );

        $criteria->setLimit(0);

        $blogs = static::getContainer()->get('blog.repository')
            ->search($criteria, Context::createDefaultContext());

        $aggregation = $blogs->getAggregations()->get('filters');

        static::assertInstanceOf(TermsResult::class, $aggregation);

        static::assertContains(self::$ids->get('red'), $aggregation->getKeys());
        static::assertNotContains(self::$ids->get('yellow'), $aggregation->getKeys());
        static::assertNotContains(self::$ids->get('XL'), $aggregation->getKeys());
        static::assertNotContains(self::$ids->get('L'), $aggregation->getKeys());
    }

    public function testNestedManyToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('category-'));

        $criteria->addFilter(
            new EqualsAnyFilter('category.blogs.properties.id', [self::$ids->get('red'), self::$ids->get('yellow')])
        );
        $criteria->addFilter(
            new EqualsAnyFilter('category.blogs.properties.id', [self::$ids->get('XL'), self::$ids->get('L')])
        );

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('category-1')));
        static::assertTrue($result->has(self::$ids->get('category-2')));
        static::assertFalse($result->has(self::$ids->get('category-3')));
    }

    public function testTranslatedFields(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new EqualsFilter('blog.properties.name', 'red')
        );
        $criteria->addFilter(
            new EqualsFilter('blog.properties.name', 'yellow')
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));
    }

    public function testContainsFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new ContainsFilter('blog.properties.name', 're')
        );
        $criteria->addFilter(
            new ContainsFilter('blog.properties.name', 'yell')
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-1')));
        static::assertFalse($result->has(self::$ids->get('blog-2')));
    }

    public function testPrefixFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        // "re" refers to the property "red" of "blog-1" and "blog-2"
        $criteria->addFilter(
            new PrefixFilter('blog.properties.name', 're')
        );
        // "yell" refers to the property "yellow" of only "blog-1"
        $criteria->addFilter(
            new PrefixFilter('blog.properties.name', 'yell')
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-1')));
        static::assertFalse($result->has(self::$ids->get('blog-2')));
    }

    public function testSuffixFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        // "ed" refers to the property "red" of "blog-1" and "blog-2"
        $criteria->addFilter(
            new SuffixFilter('blog.properties.name', 'ed')
        );
        // "low" refers to the property "yellow" of only "blog-1"
        $criteria->addFilter(
            new SuffixFilter('blog.properties.name', 'low')
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-1')));
        static::assertFalse($result->has(self::$ids->get('blog-2')));
    }

    public function testRangeFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('category-'));

        $criteria->addFilter(
            new RangeFilter('category.blogs.stock', [RangeFilter::GTE => 5])
        );

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('category-1')));
        static::assertTrue($result->has(self::$ids->get('category-2')));
        static::assertFalse($result->has(self::$ids->get('category-3')));
    }

    public function testNegatedRangeFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('category-'));

        $criteria->addFilter(
            new NandFilter([new RangeFilter('category.blogs.stock', [RangeFilter::GTE => 5])])
        );

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('category-1')));
        static::assertFalse($result->has(self::$ids->get('category-2')));
        static::assertTrue($result->has(self::$ids->get('category-3')));
        static::assertTrue($result->has(self::$ids->get('category-4')));
    }

    public function testOrFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new OrFilter([
                new EqualsFilter('blog.properties.id', self::$ids->get('red')),
                new EqualsFilter('blog.properties.id', self::$ids->get('yellow')),
            ])
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));
    }

    public function testOneToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('blog.prices.ruleId', self::$ids->get('rule-1')),
            ])
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));

        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('blog.prices.ruleId', self::$ids->get('rule-1')),
            ])
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));
    }

    public function testOneToManyWithSort(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('blog.prices.ruleId', self::$ids->get('rule-1')),
            ])
        );
        $criteria->addSorting(new FieldSorting('blog.prices.price'));

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertSame(self::$ids->get('blog-1'), $result->getIds()[0]);
        static::assertSame(self::$ids->get('blog-2'), $result->getIds()[1]);
    }

    public function testOneToManyWithSortDesc(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('blog.prices.ruleId', self::$ids->get('rule-1')),
            ])
        );
        $criteria->addSorting(new FieldSorting('blog.prices.price', FieldSorting::DESCENDING));

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertSame(self::$ids->get('blog-2'), $result->getIds()[0]);
        static::assertSame(self::$ids->get('blog-1'), $result->getIds()[1]); // blog-1 rule-1 price=100 < blog-2 rule-1 price=150, so blog-2 sorts first descending
    }

    public function testOneToManyWithGrouping(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('blog.prices.ruleId', self::$ids->get('rule-1')),
            ])
        );
        $criteria->addGroupField(new FieldGrouping('blog.prices.ruleId'));

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        // GROUP BY collapses both blog-1 and blog-2 (both have rule-1) into one row;
        // MySQL picks an arbitrary representative, so we only assert the count and that it is one of the valid blogs.
        static::assertTrue(
            $result->has(self::$ids->get('blog-1')) || $result->has(self::$ids->get('blog-2'))
        );
    }

    public function testOneToManyWithMultipleFilters(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new EqualsFilter('blog.prices.ruleId', self::$ids->get('rule-1'))
        );
        $criteria->addFilter(
            new EqualsFilter('blog.prices.ruleId', self::$ids->get('rule-2'))
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));
    }

    public function testManyToOne(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('category-'));

        $criteria->addFilter(
            new EqualsFilter('category.blogs.manufacturer.id', self::$ids->get('manufacturer-1'))
        );
        $criteria->addFilter(
            new EqualsFilter('category.blogs.manufacturer.name', 'manufacturer-1')
        );

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('category-1')));
        static::assertTrue($result->has(self::$ids->get('category-2')));
        static::assertFalse($result->has(self::$ids->get('category-3')));
    }

    public function testManyToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new EqualsFilter('blog.properties.id', self::$ids->get('red'))
        );
        $criteria->addFilter(
            new EqualsFilter('blog.properties.id', self::$ids->get('yellow'))
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));
    }

    public function testManyToManyWithMultiJoinGroup(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new OrFilter([
                new AndFilter([
                    new EqualsFilter('blog.properties.id', self::$ids->get('yellow')),
                    new EqualsFilter('blog.properties.name', 'yellow'),
                ]),
                new AndFilter([
                    new EqualsFilter('blog.properties.id', self::$ids->get('S')),
                    new EqualsFilter('blog.properties.name', 'S'),
                ]),
            ])
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-1')));
        static::assertTrue($result->has(self::$ids->get('blog-2')));
    }

    public function testManyToManyWithOneFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('blog.properties.id', self::$ids->get('yellow')),
                new EqualsFilter('blog.properties.name', 'yellow'),
            ])
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));
    }

    public function testOneToManyTranslated(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('manufacturer-'));

        $criteria->addFilter(
            new EqualsFilter('blog_manufacturer.blogs.name', 'blog-1')
        );
        $criteria->addFilter(
            new EqualsFilter('blog_manufacturer.blogs.stock', 10)
        );

        $result = static::getContainer()->get('blog_manufacturer.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('manufacturer-1')));
        static::assertFalse($result->has(self::$ids->get('manufacturer-2')));

        $criteria = new Criteria(self::$ids->prefixed('manufacturer-'));

        $criteria->addFilter(
            new ContainsFilter('blog_manufacturer.blogs.name', 'blog')
        );
        $criteria->addFilter(
            new RangeFilter('blog_manufacturer.blogs.stock', [RangeFilter::GT => 1])
        );

        $result = static::getContainer()->get('blog_manufacturer.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('manufacturer-1')));
        static::assertTrue($result->has(self::$ids->get('manufacturer-2')));
    }

    public function testManyToOneTranslated(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new NorFilter([
                new EqualsFilter('blog.manufacturer.id', null),
                new EqualsFilter('blog.manufacturer.name', 'test'),
            ]),
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));

        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new ContainsFilter('blog.manufacturer.name', 'manufacturer')
        );
        $criteria->addFilter(
            new EqualsAnyFilter('blog.manufacturer.id', self::$ids->getList(['manufacturer-1', 'manufacturer-2']))
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));
    }

    public function testManyToManyTranslated(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new EqualsFilter('blog.properties.name', 'red')
        );
        $criteria->addFilter(
            new EqualsFilter('blog.properties.name', 'yellow')
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-1')));
        static::assertFalse($result->has(self::$ids->get('blog-2')));
    }

    public function testOneToManyInherited(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('blog.prices.ruleId', self::$ids->get('rule-1')),
            ])
        );

        $result = Context::createDefaultContext()->enableInheritance(static fn (Context $context) => static::getContainer()->get('blog.repository')
            ->searchIds($criteria, $context));

        static::assertSame(3, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));
        static::assertTrue($result->has(self::$ids->get('blog-1-variant')));
    }

    public function testManyToOneInherited(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('blog.manufacturer.id', self::$ids->get('manufacturer-2')),
            ])
        );

        $result = Context::createDefaultContext()->enableInheritance(static fn (Context $context) => static::getContainer()->get('blog.repository')
            ->searchIds($criteria, $context));

        static::assertSame(3, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));
        static::assertTrue($result->has(self::$ids->get('blog-1-variant')));
        static::assertTrue($result->has(self::$ids->get('blog-3')));
    }

    public function testManyToManyInherited(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new EqualsFilter('blog.properties.id', self::$ids->get('red'))
        );
        $criteria->addFilter(
            new EqualsFilter('blog.properties.id', self::$ids->get('yellow'))
        );

        $result = Context::createDefaultContext()->enableInheritance(static fn (Context $context) => static::getContainer()->get('blog.repository')
            ->searchIds($criteria, $context));

        static::assertSame(2, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));
        static::assertTrue($result->has(self::$ids->get('blog-1-variant')));
    }

    public function testHasOneToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('blog.prices.id', null),
            ])
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));
    }

    public function testHasManyToOne(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('blog.manufacturer.id', null),
            ])
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));
    }

    public function testHasManyToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('blog.manufacturer.id', null),
            ])
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-2')));
        static::assertTrue($result->has(self::$ids->get('blog-1')));
        static::assertFalse($result->has(self::$ids->get('blog-3')));
    }

    public function testHasNotOneToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new EqualsFilter('blog.prices.id', null)
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-3')));
        static::assertTrue($result->has(self::$ids->get('blog-1-variant')));
        static::assertFalse($result->has(self::$ids->get('blog-1')));
        static::assertFalse($result->has(self::$ids->get('blog-2')));
    }

    public function testHasNotManyToOne(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new EqualsFilter('blog.manufacturer.id', null)
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('blog-3')));
        static::assertTrue($result->has(self::$ids->get('blog-1-variant')));
        static::assertFalse($result->has(self::$ids->get('blog-2')));
        static::assertFalse($result->has(self::$ids->get('blog-1')));
    }

    public function testHasNotManyToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('blog-'));
        $criteria->addFilter(
            new EqualsFilter('blog.properties.id', null)
        );

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('blog-2')));
        static::assertFalse($result->has(self::$ids->get('blog-1')));
        static::assertTrue($result->has(self::$ids->get('blog-3')));
        static::assertTrue($result->has(self::$ids->get('blog-1-variant')));
    }

    public function testEqualsNullWithUnmappedField(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('unmappedField', null));

        if (Feature::isActive('v6.8.0.0')) {
            static::expectExceptionObject(DataAbstractionLayerException::unmappedField(
                'unmappedField',
                static::getContainer()->get(BlogDefinition::class)
            ));
        } else {
            static::expectException(UnmappedFieldException::class);
        }
        static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());
    }

    private static function insertTestData(): void
    {
        $container = KernelLifecycleManager::getKernel()->getContainer();

        $blogs = [
            (new BlogBuilder(self::$ids, 'blog-1', 10, 'tax'))
                ->price(15, 10)
                ->manufacturer('manufacturer-1')
                ->property('red', 'color')
                ->property('yellow', 'color')
                ->property('XL', 'size')
                ->property('L', 'size')
                ->category('category-1')
                ->category('category-2')
                ->prices('rule-1', 100)
                ->prices('rule-2', 150)
                ->build(),

            (new BlogBuilder(self::$ids, 'blog-1-variant', 10, 'tax'))
                ->parent('blog-1')
                ->build(),

            (new BlogBuilder(self::$ids, 'blog-2', 3, 'tax'))
                ->price(15, 10)
                ->manufacturer('manufacturer-2')
                ->property('red', 'color')
                ->property('S', 'size')
                ->category('category-1')
                ->category('category-3')
                ->prices('rule-1', 150)
                ->build(),

            (new BlogBuilder(self::$ids, 'blog-3', 3, 'tax'))
                ->price(15, 10)
                ->category('category-4')
                ->build(),
        ];

        $container->get('blog.repository')
            ->create($blogs, Context::createDefaultContext());

        $adminUserId = $container->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM `admin_user` LIMIT 1');

        $media = [
            ['id' => self::$ids->create('with-avatar')],
            ['id' => self::$ids->create('without-avatar')],
        ];

        $container->get('media.repository')
            ->create($media, Context::createDefaultContext());

        $avatar = [
            'id' => $adminUserId,
            'avatarId' => self::$ids->get('with-avatar'),
        ];

        $container->get('admin_user.repository')
            ->update([$avatar], Context::createDefaultContext());

        $result = $container->get('blog.repository')
            ->searchIds(new Criteria(self::$ids->prefixed('blog-')), Context::createDefaultContext());

        static::assertSame(\count($blogs), $result->getTotal());
    }
}
