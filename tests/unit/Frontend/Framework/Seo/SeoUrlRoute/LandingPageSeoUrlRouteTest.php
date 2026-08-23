<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Frontend\Framework\FrontendFrameworkException;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;

/**
 * @internal
 */
#[CoversClass(LandingPageSeoUrlRoute::class)]
class LandingPageSeoUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $landingPageDefinition = static::createStub(LandingPageDefinition::class);
        $route = new LandingPageSeoUrlRoute($landingPageDefinition);

        $config = $route->getConfig();
        static::assertSame($landingPageDefinition, $config->getDefinition());
        static::assertSame(LandingPageSeoUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame(LandingPageSeoUrlRoute::DEFAULT_TEMPLATE, $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
    }

    public function testCriteria(): void
    {
        $route = new LandingPageSeoUrlRoute(static::createStub(LandingPageDefinition::class));

        $criteria = new Criteria();
        $channel = new ChannelEntity();
        $channel->setId('test');
        $route->prepareCriteria($criteria, $channel);

        static::assertTrue($criteria->hasEqualsFilter('active'));
        static::assertTrue($criteria->hasEqualsFilter('channels.id'));
    }

    public function testMappingWithInvalidEntity(): void
    {
        $route = new LandingPageSeoUrlRoute(static::createStub(LandingPageDefinition::class));

        $this->expectExceptionObject(FrontendFrameworkException::invalidArgument('SEO URL Mapping expects argument to be a LandingPageEntity'));
        $route->getMapping(new ArrayEntity(), new ChannelEntity());
    }

    public function testMapping(): void
    {
        $route = new LandingPageSeoUrlRoute(static::createStub(LandingPageDefinition::class));

        $landingPage = new LandingPageEntity();
        $landingPage->setId('test');
        $data = $route->getMapping($landingPage, new ChannelEntity());

        static::assertNull($data->getError());
        static::assertSame($landingPage, $data->getEntity());
        static::assertSame(['landingPageId' => 'test'], $data->getInfoPathContext());

        $context = $data->getSeoPathInfoContext();
        static::assertArrayHasKey('landingPage', $context);
        static::assertSame($landingPage->jsonSerialize(), $context['landingPage']);
    }
}
