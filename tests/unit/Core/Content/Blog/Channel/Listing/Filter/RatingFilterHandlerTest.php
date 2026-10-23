<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Listing\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Listing\Filter;
use Contena\Core\Content\Blog\Channel\Listing\Filter\RatingListingFilterHandler;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RatingListingFilterHandler::class)]
class RatingFilterHandlerTest extends TestCase
{
    public function testFilterCanBeSkipped(): void
    {
        $result = (new RatingListingFilterHandler())->create(
            new Request([], ['rating-filter' => false]),
            static::createStub(ChannelContext::class)
        );

        static::assertNull($result);
    }

    #[DataProvider('filterProvider')]
    public function testFilterCanBeCreated(int $input): void
    {
        $result = (new RatingListingFilterHandler())->create(
            new Request(['rating' => $input]),
            static::createStub(ChannelContext::class)
        );

        $expected = new Filter(
            'rating',
            true,
            [
                new FilterAggregation(
                    'rating-exists',
                    new MaxAggregation('rating', 'blog.ratingAverage'),
                    [new RangeFilter('blog.ratingAverage', [RangeFilter::GTE => 0])]
                ),
            ],
            new RangeFilter('blog.ratingAverage', [
                RangeFilter::GTE => $input,
            ]),
            $input
        );

        static::assertEquals($expected, $result);
    }

    public static function filterProvider(): \Generator
    {
        yield 'rating better than 4' => [4];
        yield 'rating better than 3' => [3];
    }
}
