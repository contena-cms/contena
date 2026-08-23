<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Seo\SeoUrlRoute\BlogChannelApiUrlRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(BlogChannelApiUrlRoute::class)]
class BlogChannelApiUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $definition = new BlogDefinition();
        $config = new BlogChannelApiUrlRoute($definition)->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame(BlogChannelApiUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame('channel-api.blog.detail', $config->getRouteName());
        static::assertSame('', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
        static::assertSame(['blogId' => 'abc123'], $config->getPrimaryKeyParameter('abc123'));
    }

    public function testPrepareCriteriaScopesToChannelVisibility(): void
    {
        $criteria = new Criteria();
        $channel = new ChannelEntity();
        $channel->setId('channel-id');

        new BlogChannelApiUrlRoute(new BlogDefinition())->prepareCriteria($criteria, $channel);

        $filters = $criteria->getFilters();
        static::assertCount(1, $filters);
        static::assertEquals(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('active', true),
                new RangeFilter('visibilities.visibility', [RangeFilter::GTE => BlogVisibilityDefinition::VISIBILITY_LINK]),
                new EqualsFilter('visibilities.channelId', 'channel-id'),
            ]),
            $filters[0]
        );
    }
}
