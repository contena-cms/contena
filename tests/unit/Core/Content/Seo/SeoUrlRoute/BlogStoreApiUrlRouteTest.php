<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Seo\SeoUrlRoute\BlogStoreApiUrlRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(BlogStoreApiUrlRoute::class)]
class BlogStoreApiUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $definition = new BlogDefinition();
        $config = (new BlogStoreApiUrlRoute($definition))->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame(BlogStoreApiUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame('store-api.blog.detail', $config->getRouteName());
        static::assertSame('', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
        static::assertSame(['blogId' => 'abc123'], $config->getPrimaryKeyParameter('abc123'));
    }

    public function testPrepareCriteriaScopesToChannelVisibility(): void
    {
        $criteria = new Criteria();
        $channel = new ChannelEntity();
        $channel->setId('sales-channel-id');

        (new BlogStoreApiUrlRoute(new BlogDefinition()))->prepareCriteria($criteria, $channel);

        static::assertEquals(
            [
                new EqualsFilter('active', true),
                new EqualsFilter('visibilities.channelId', 'sales-channel-id'),
            ],
            $criteria->getFilters()
        );
        static::assertTrue($criteria->hasAssociation('options'));
    }
}
