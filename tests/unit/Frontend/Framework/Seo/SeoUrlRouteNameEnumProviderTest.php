<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRouteNameEnumProvider;

/**
 * @internal
 */
#[CoversClass(SeoUrlRouteNameEnumProvider::class)]
class SeoUrlRouteNameEnumProviderTest extends TestCase
{
    public function testIsSupported(): void
    {
        $provider = new SeoUrlRouteNameEnumProvider(static::createStub(SeoUrlRouteRegistry::class));

        static::assertTrue($provider->isSupported(SeoUrlDefinition::ENTITY_NAME, 'routeName'));
        static::assertFalse($provider->isSupported('blog', 'routeName'));
        static::assertFalse($provider->isSupported(SeoUrlDefinition::ENTITY_NAME, 'name'));
    }

    public function testGetEnumValues(): void
    {
        $registry = static::createStub(SeoUrlRouteRegistry::class);
        $registry->method('getSeoUrlRoutes')->willReturn([
            BlogPageSeoUrlRoute::ROUTE_NAME => new \stdClass(),
            'frontend.navigation.page' => new \stdClass(),
        ]);

        $provider = new SeoUrlRouteNameEnumProvider($registry);

        static::assertSame(
            [BlogPageSeoUrlRoute::ROUTE_NAME, 'frontend.navigation.page'],
            $provider->getChoices()
        );
    }
}
