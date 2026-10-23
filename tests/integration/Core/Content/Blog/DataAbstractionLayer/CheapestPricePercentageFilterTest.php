<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class CheapestPricePercentageFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    public function testBlogWithoutListPriceIsExcludedByPercentageLessThan100(): void
    {
        $discountedId = Uuid::randomHex();
        $noListPriceId = Uuid::randomHex();

        $this->createBlog($discountedId, 90.0, 120.0);
        $this->createBlog($noListPriceId, 90.0, null);

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('blog.cheapestPrice.percentage', [RangeFilter::LT => 100]));

        $result = $this->searchIds($criteria);

        static::assertContains($discountedId, $result->getIds());
        static::assertNotContains($noListPriceId, $result->getIds());
    }

    public function testPercentageLessThanOrEqual100IncludesAllBlogsWithListPrice(): void
    {
        $heavilyDiscounted = Uuid::randomHex();
        $slightlyDiscounted = Uuid::randomHex();
        $noDiscount = Uuid::randomHex();
        $noListPrice = Uuid::randomHex();

        $this->createBlog($heavilyDiscounted, 50.0, 200.0);
        $this->createBlog($slightlyDiscounted, 95.0, 100.0);
        $this->createBlog($noDiscount, 100.0, 100.0);
        $this->createBlog($noListPrice, 100.0, null);

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('blog.cheapestPrice.percentage', [RangeFilter::LTE => 100]));

        $result = $this->searchIds($criteria);

        static::assertContains($heavilyDiscounted, $result->getIds());
        static::assertContains($slightlyDiscounted, $result->getIds());
        static::assertContains($noDiscount, $result->getIds(), 'percentage=100 should match LTE 100');
        static::assertNotContains($noListPrice, $result->getIds(), 'blog without list price must not match');
    }

    public function testPercentageLessThan100ExcludesNonDiscountedBlogs(): void
    {
        $discounted = Uuid::randomHex();
        $noDiscount = Uuid::randomHex();
        $noListPrice = Uuid::randomHex();

        $this->createBlog($discounted, 90.0, 120.0);
        $this->createBlog($noDiscount, 100.0, 100.0);
        $this->createBlog($noListPrice, 100.0, null);

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('blog.cheapestPrice.percentage', [RangeFilter::LT => 100]));

        $result = $this->searchIds($criteria);

        static::assertContains($discounted, $result->getIds());
        static::assertNotContains($noDiscount, $result->getIds(), 'percentage=100 should not match LT 100');
        static::assertNotContains($noListPrice, $result->getIds(), 'blog without list price must not match');
    }

    public function testPercentageRangeFilterWithBothBounds(): void
    {
        $discount25 = Uuid::randomHex();
        $discount50 = Uuid::randomHex();
        $discount75 = Uuid::randomHex();
        $noListPrice = Uuid::randomHex();

        $this->createBlog($discount25, 75.0, 100.0);
        $this->createBlog($discount50, 50.0, 100.0);
        $this->createBlog($discount75, 25.0, 100.0);
        $this->createBlog($noListPrice, 50.0, null);

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('blog.cheapestPrice.percentage', [
            RangeFilter::GTE => 50,
            RangeFilter::LTE => 75,
        ]));

        $result = $this->searchIds($criteria);

        static::assertContains($discount25, $result->getIds(), 'percentage=75 should match GTE 50');
        static::assertContains($discount50, $result->getIds(), 'percentage=50 should match LTE 75');
        static::assertNotContains($discount75, $result->getIds(), 'percentage=25 is below lower bound');
        static::assertNotContains($noListPrice, $result->getIds(), 'blog without list price should be excluded');
    }

    public function testPercentageSortingExcludesNullValues(): void
    {
        $discountedId = Uuid::randomHex();
        $noListPriceId = Uuid::randomHex();

        $this->createBlog($discountedId, 80.0, 100.0);
        $this->createBlog($noListPriceId, 80.0, null);

        $criteria = new Criteria([$discountedId, $noListPriceId]);
        $criteria->addSorting(new FieldSorting('blog.cheapestPrice.percentage', FieldSorting::ASCENDING));
        $criteria->addFilter(new RangeFilter('blog.cheapestPrice.percentage', [RangeFilter::LT => 100]));

        $result = $this->searchIds($criteria);

        static::assertContains($discountedId, $result->getIds());
        static::assertNotContains($noListPriceId, $result->getIds());
    }

    public function testPercentageEquals100MatchesNonDiscountedWithListPrice(): void
    {
        $nonDiscounted = Uuid::randomHex();
        $discounted = Uuid::randomHex();
        $noListPrice = Uuid::randomHex();

        $this->createBlog($nonDiscounted, 100.0, 100.0);
        $this->createBlog($discounted, 90.0, 120.0);
        $this->createBlog($noListPrice, 100.0, null);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.cheapestPrice.percentage', 100));

        $result = $this->searchIds($criteria);

        static::assertContains($nonDiscounted, $result->getIds(), 'Blog with price=listPrice (percentage=100) should match');
        static::assertNotContains($discounted, $result->getIds(), 'Discounted blog should not match percentage=100');
        static::assertNotContains($noListPrice, $result->getIds(), 'Blog without list price should not match percentage=100');
    }

    public function testPriceFieldPercentageFilterExcludesBlogsWithoutListPrice(): void
    {
        $discountedId = Uuid::randomHex();
        $noListPriceId = Uuid::randomHex();

        $this->createBlog($discountedId, 90.0, 120.0);
        $this->createBlog($noListPriceId, 90.0, null);

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('blog.price.percentage', [RangeFilter::LT => 100]));

        $result = $this->searchIds($criteria);

        static::assertContains($discountedId, $result->getIds());
        static::assertNotContains($noListPriceId, $result->getIds());
    }

    private function searchIds(Criteria $criteria): IdSearchResult
    {
        return static::getContainer()->get('channel.blog.repository')
            ->searchIds($criteria, $this->channelContext);
    }

    private function createBlog(string $id, float $priceGross, ?float $listGross): void
    {
        $price = [
            'currencyId' => Defaults::CURRENCY,
            'gross' => $priceGross,
            'net' => $priceGross,
            'linked' => false,
        ];

        if ($listGross !== null) {
            $price['listPrice'] = [
                'gross' => $listGross,
                'net' => $listGross,
                'linked' => false,
            ];
        }

        static::getContainer()->get('blog.repository')->create([[
            'id' => $id,
            'blogNumber' => $id,
            'name' => 'Percentage test ' . $id,
            'stock' => 10,
            'active' => true,
            'price' => [$price],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                [
                    'channelId' => TestDefaults::CHANNEL,
                    'visibility' => 30,
                ],
            ],
        ]], $this->channelContext->getContext());
    }
}
