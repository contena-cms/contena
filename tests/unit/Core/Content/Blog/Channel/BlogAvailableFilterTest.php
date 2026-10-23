<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(BlogAvailableFilter::class)]
class BlogAvailableFilterTest extends TestCase
{
    public function testCreatesBlogAvailableFilter(): void
    {
        $channelId = TestDefaults::CHANNEL;
        $visibility = BlogVisibilityDefinition::VISIBILITY_ALL;

        $filter = new BlogAvailableFilter($channelId, $visibility);

        static::assertSame($channelId, $filter->getChannelId());
        static::assertSame($visibility, $filter->getVisibility());
        static::assertCount(3, $filter->getQueries());
        static::assertSame([
            (new EqualsFilter('blog.visibilities.channelId', $channelId))->jsonSerialize(),
            (new EqualsFilter('blog.active', true))->jsonSerialize(),
        ], array_map(
            static fn (Filter $filter): array => $filter->jsonSerialize(),
            $filter->getQueries()
        ));
    }
}
