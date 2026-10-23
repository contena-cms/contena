<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Listing\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Listing\Filter;
use Contena\Core\Content\Blog\Channel\Listing\Filter\ShippingFreeListingFilterHandler;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ShippingFreeListingFilterHandler::class)]
class ShippingFreeFilterHandlerTest extends TestCase
{
    public function testFilterCanBeSkipped(): void
    {
        $result = (new ShippingFreeListingFilterHandler())->create(
            new Request([], ['shipping-free-filter' => false]),
            static::createStub(ChannelContext::class)
        );

        static::assertNull($result);
    }

    #[DataProvider('filterProvider')]
    public function testFilterCanBeCreated(bool $input): void
    {
        $result = (new ShippingFreeListingFilterHandler())->create(
            new Request(['shipping-free' => $input]),
            static::createStub(ChannelContext::class)
        );

        $expected = new Filter(
            'shipping-free',
            $input,
            [
                new FilterAggregation(
                    'shipping-free-filter',
                    new MaxAggregation('shipping-free', 'blog.shippingFree'),
                    [new EqualsFilter('blog.shippingFree', true)]
                ),
            ],
            new EqualsFilter('blog.shippingFree', true),
            $input
        );

        static::assertEquals($expected, $result);
    }

    public static function filterProvider(): \Generator
    {
        yield 'shipping free' => [true];
        yield 'not shipping free' => [false];
    }
}
