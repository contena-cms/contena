<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\Seo\SeoUrlRoute\LandingPageChannelApiUrlRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(LandingPageChannelApiUrlRoute::class)]
class LandingPageChannelApiUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $definition = new LandingPageDefinition();
        $config = new LandingPageChannelApiUrlRoute($definition)->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame(LandingPageChannelApiUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame('channel-api.landing-page.detail', $config->getRouteName());
        static::assertSame('', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
        static::assertSame(['landingPageId' => 'abc123'], $config->getPrimaryKeyParameter('abc123'));
    }

    public function testPrepareCriteriaScopesToChannel(): void
    {
        $criteria = new Criteria();
        $channel = new ChannelEntity();
        $channel->setId('channel-id');

        new LandingPageChannelApiUrlRoute(new LandingPageDefinition())->prepareCriteria($criteria, $channel);

        static::assertEquals(
            [
                new EqualsFilter('active', true),
                new EqualsFilter('channels.id', 'channel-id'),
            ],
            $criteria->getFilters()
        );
    }
}
