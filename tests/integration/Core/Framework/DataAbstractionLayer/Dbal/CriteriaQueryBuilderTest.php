<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Listing\ResolveCriteriaBlogListingRoute;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class CriteriaQueryBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public IdsCollection $ids;

    /**
     * This test checks listing sorting behavior affected by MySQL's GROUP BY handling.
     *
     * Contena disables ONLY_FULL_GROUP_BY, allowing queries that may return non-deterministic
     * results when selecting columns not functionally dependent on the GROUP BY clause.
     * See: https://dev.mysql.com/doc/refman/8.4/en/group-by-handling.html
     *
     * The CriteriaQueryBuilder generates such a query. Without the fix, results may vary between
     * runs; with the fix, the outcome is deterministic and the test should always pass.
     *
     * A specific ID set is used to reproduce the issue. For reference, the following ID set
     * does NOT trigger the problem and is noted here for completeness:
     * ['s1' => '00000000000000000000000000000001',
     *  's1.1' => '00000000000000000000000000000002',
     *  's1.2' => '00000000000000000000000000000003',
     *  'p1' => '00000000000000000000000000000004',
     *  'p1.1' => '00000000000000000000000000000005',
     *  'p1.2' => '00000000000000000000000000000006']
     *
     * Changing the data may prevent the issue from appearing, so edit with caution.
     */
    public function testSortingByCheapestPrice(): void
    {
        $this->ids = new IdsCollection();
        $this->createExampleBlogs();

        $context = static::getContainer()->get(ChannelContextFactory::class)->create(
            'anytokenstring',
            TestDefaults::CHANNEL
        );

        static::assertSame(
            [
                $this->ids->get('p1'),
                $this->ids->get('s1'),
            ],
            array_values($this->orderListing('price-asc', $context))
        );

        static::assertSame(
            [
                $this->ids->get('s1'),
                $this->ids->get('p1'),
            ],
            array_values($this->orderListing('price-desc', $context))
        );
    }

    private function createExampleBlogs(): void
    {
        $this->ids->set('s1', '0198bd43b1c37964a5c1ecbd2d89fd6e');
        $this->ids->set('s1.1', '0198c286060673308abe19bf59ccb004');
        $this->ids->set('s1.2', '0198c286209972e68646a54bf8211144');
        $this->ids->set('p1', '0198bd4417f471a69742ca2390243653');
        $this->ids->set('p1.1', '0198bd446e0973448206e3197b2d24ea');
        $this->ids->set('p1.2', '0198bd446e077084984f95175b2bea27');

        $s1 = (new BlogBuilder($this->ids, 's1'))
            ->price(100)
            ->category('test-category')
            ->variantListingConfig(['displayParent' => true])
            ->visibility()
            ->variant(
                (new BlogBuilder($this->ids, 's1.1'))
                    ->price(110)
                    ->build()
            )
            ->variant(
                (new BlogBuilder($this->ids, 's1.2'))
                    ->price(120)
                    ->build()
            )
            ->build();
        $p1 = (new BlogBuilder($this->ids, 'p1'))
            ->price(100)
            ->category('test-category')
            ->variantListingConfig(['displayParent' => true])
            ->visibility()
            ->variant(
                (new BlogBuilder($this->ids, 'p1.1'))
                    ->price(50)
                    ->build()
            )
            ->variant(
                (new BlogBuilder($this->ids, 'p1.2'))
                    ->price(200)
                    ->build()
            )
            ->build();

        static::getContainer()->get('blog.repository')->create([$s1, $p1], Context::createDefaultContext());
    }

    /**
     * @return string[]
     */
    private function orderListing(string $dir, ChannelContext $context): array
    {
        $result = static::getContainer()->get(ResolveCriteriaBlogListingRoute::class)->load(
            $this->ids->get('test-category'),
            new Request(query: ['order' => $dir]),
            $context,
            new Criteria()
        );

        return $result->getResult()->getEntities()->getIds();
    }
}
