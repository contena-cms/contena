<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Seo\SeoUrlRoute\CategoryChannelApiUrlRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(CategoryChannelApiUrlRoute::class)]
class CategoryChannelApiUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $definition = new CategoryDefinition();
        $config = new CategoryChannelApiUrlRoute($definition)->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame(CategoryChannelApiUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame('channel-api.category.detail', $config->getRouteName());
        static::assertSame('', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
        static::assertSame(['navigationId' => 'abc123'], $config->getPrimaryKeyParameter('abc123'));
    }

    public function testPrepareCriteriaScopesToTheChannelCategoryTrees(): void
    {
        $criteria = new Criteria();
        $channel = new ChannelEntity();
        $channel->setId('channel-id');
        $channel->setNavigationCategoryId('navigation-id');
        $channel->setServiceCategoryId('service-id');

        new CategoryChannelApiUrlRoute(new CategoryDefinition())->prepareCriteria($criteria, $channel);

        $filters = $criteria->getFilters();
        static::assertCount(3, $filters);

        static::assertEquals(new EqualsFilter('active', true), $filters[0]);
        static::assertInstanceOf(NotFilter::class, $filters[1]);

        static::assertInstanceOf(MultiFilter::class, $filters[2]);
        static::assertSame(MultiFilter::CONNECTION_OR, $filters[2]->getOperator());
        static::assertEquals(
            [
                new ContainsFilter('path', '|navigation-id|'),
                new ContainsFilter('path', '|service-id|'),
            ],
            $filters[2]->getQueries()
        );
    }
}
