<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\Seo\SeoUrlRoute\LandingPageStoreApiUrlRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(LandingPageStoreApiUrlRoute::class)]
class LandingPageStoreApiUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $definition = new LandingPageDefinition();
        $config = (new LandingPageStoreApiUrlRoute($definition))->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame(LandingPageStoreApiUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame('store-api.landing-page.detail', $config->getRouteName());
        static::assertSame('', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
        static::assertSame(['landingPageId' => 'abc123'], $config->getPrimaryKeyParameter('abc123'));
    }

    public function testPrepareCriteriaScopesToChannel(): void
    {
        $criteria = new Criteria();
        $channel = new ChannelEntity();
        $channel->setId('sales-channel-id');

        (new LandingPageStoreApiUrlRoute(new LandingPageDefinition()))->prepareCriteria($criteria, $channel);

        static::assertEquals(
            [
                new EqualsFilter('active', true),
                new EqualsFilter('channels.id', 'sales-channel-id'),
            ],
            $criteria->getFilters()
        );
    }
}
