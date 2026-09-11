<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlRoute;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlRoute::class)]
class AppSeoUrlRouteTest extends TestCase
{
    public function testConfigGeneratesPathsThroughTheScriptEndpointOfTheDeclaredHook(): void
    {
        $definition = static::createStub(BlogDefinition::class);

        $route = new AppSeoUrlRoute(
            $definition,
            'frontend.app.SwagSeoUrlApp.blog-teaser',
            'blog-teaser',
            '{{ blog.translated.name }}'
        );

        $config = $route->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame('frontend.app.SwagSeoUrlApp.blog-teaser', $config->getRouteName());
        static::assertSame('frontend.script_endpoint', $config->getTargetRouteName());
        static::assertSame(AppSeoUrlRoute::TARGET_ROUTE, $config->getTargetRouteName());
        static::assertSame('{{ blog.translated.name }}', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
    }

    public function testPrimaryKeyIsPassedAsIdNextToTheHookParameter(): void
    {
        $route = new AppSeoUrlRoute(
            static::createStub(BlogDefinition::class),
            'frontend.app.SwagSeoUrlApp.blog-teaser',
            'blog-teaser',
            '{{ blog.translated.name }}'
        );

        static::assertSame(
            ['hook' => 'blog-teaser', 'id' => 'c1a5b1a3c1a5b1a3c1a5b1a3c1a5b1a3'],
            $route->getConfig()->getPrimaryKeyParameter('c1a5b1a3c1a5b1a3c1a5b1a3c1a5b1a3')
        );
    }

    public function testPrepareCriteriaLeavesTheCriteriaUntouched(): void
    {
        $route = new AppSeoUrlRoute(
            static::createStub(BlogDefinition::class),
            'frontend.app.SwagSeoUrlApp.blog-teaser',
            'blog-teaser',
            '{{ blog.translated.name }}'
        );

        $criteria = new Criteria();
        $route->prepareCriteria($criteria, new ChannelEntity());

        static::assertSame([], $criteria->getFilters());
        static::assertSame([], $criteria->getAssociations());
        static::assertSame([], $criteria->getSorting());
    }
}
