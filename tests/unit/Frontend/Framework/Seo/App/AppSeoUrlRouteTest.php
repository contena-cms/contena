<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Frontend\Framework\Seo\App\AppEntitySeoUrlConfig;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlRoute;

/**
 * @internal
 */
#[CoversClass(AppSeoUrlRoute::class)]
class AppSeoUrlRouteTest extends TestCase
{
    public function testConfigGeneratesPathsThroughTheScriptEndpointOfTheDeclaredHook(): void
    {
        $definition = new BlogDefinition();

        $config = (new AppSeoUrlRoute($definition, $this->seoUrl()))->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame('frontend.app.SwagSeoUrlApp.blog-teaser', $config->getRouteName());
        static::assertSame('frontend.script_endpoint', $config->getTargetRouteName());
        static::assertSame(AppSeoUrlRoute::TARGET_ROUTE, $config->getTargetRouteName());
        static::assertSame('teaser/{{ blog.blogNumber }}', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
    }

    public function testPrimaryKeyIsPassedAsIdNextToTheHookParameter(): void
    {
        $route = new AppSeoUrlRoute(new BlogDefinition(), $this->seoUrl());

        static::assertSame(
            ['hook' => 'teaser-page', 'id' => 'c1a5b1a3c1a5b1a3c1a5b1a3c1a5b1a3'],
            $route->getConfig()->getPrimaryKeyParameter('c1a5b1a3c1a5b1a3c1a5b1a3c1a5b1a3')
        );
    }

    public function testPrepareCriteriaLeavesTheCriteriaUntouched(): void
    {
        $route = new AppSeoUrlRoute(new BlogDefinition(), $this->seoUrl());

        $criteria = new Criteria();
        $route->prepareCriteria($criteria, new ChannelEntity());

        static::assertEquals(new Criteria(), $criteria);
    }

    public function testRouteNamesAreNamespacedByTheDeclaringApp(): void
    {
        static::assertSame('frontend.app.SwagSeoUrlApp.', AppSeoUrlRoute::routeNamePrefix('SwagSeoUrlApp'));
        static::assertSame('frontend.app.SwagSeoUrlApp.imprint', AppSeoUrlRoute::buildRouteName('SwagSeoUrlApp', 'imprint'));
    }

    private function seoUrl(): AppEntitySeoUrlConfig
    {
        return new AppEntitySeoUrlConfig(
            name: 'blog-teaser',
            routeName: 'frontend.app.SwagSeoUrlApp.blog-teaser',
            hook: 'teaser-page',
            entityName: 'blog',
            defaultTemplate: 'teaser/{{ blog.blogNumber }}',
        );
    }
}
