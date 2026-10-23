<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteLoaderInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * @internal
 */
#[CoversClass(SeoUrlRouteRegistry::class)]
class SeoUrlRouteRegistryTest extends TestCase
{
    public function testFindByRouteNameReturnsCompileTimeRoute(): void
    {
        $route = $this->createRoute('frontend.detail.page', 'blog');

        $registry = new SeoUrlRouteRegistry([$route]);

        static::assertSame($route, $registry->findByRouteName('frontend.detail.page'));
        static::assertNull($registry->findByRouteName('frontend.app.MyApp.blog-detail'));
    }

    public function testFindByRouteNameReturnsRouteProvidedByALoader(): void
    {
        $appRoute = $this->createRoute('frontend.app.MyApp.blog-detail', 'ce_blog');

        $registry = new SeoUrlRouteRegistry([], [new StaticSeoUrlRouteLoader([$appRoute])]);

        static::assertSame($appRoute, $registry->findByRouteName('frontend.app.MyApp.blog-detail'));
    }

    public function testCompileTimeRouteWinsOverALoaderRouteOfTheSameName(): void
    {
        $compileTimeRoute = $this->createRoute('frontend.detail.page', 'blog');
        $loaderRoute = $this->createRoute('frontend.detail.page', 'blog');

        $registry = new SeoUrlRouteRegistry([$compileTimeRoute], [new StaticSeoUrlRouteLoader([$loaderRoute])]);

        static::assertSame($compileTimeRoute, $registry->findByRouteName('frontend.detail.page'));
        static::assertSame([$compileTimeRoute], $registry->findByDefinition('blog'));
        static::assertSame(['frontend.detail.page' => $compileTimeRoute], [...$registry->getSeoUrlRoutes()]);
    }

    public function testFindByDefinitionReturnsCompileTimeAndLoaderRoutes(): void
    {
        $compileTimeRoute = $this->createRoute('frontend.detail.page', 'blog');
        $appRoute = $this->createRoute('frontend.app.MyApp.blog-detail', 'blog');
        $otherAppRoute = $this->createRoute('frontend.app.MyApp.blog-detail', 'ce_blog');

        $registry = new SeoUrlRouteRegistry(
            [$compileTimeRoute],
            [new StaticSeoUrlRouteLoader([$appRoute, $otherAppRoute])]
        );

        static::assertSame([$compileTimeRoute, $appRoute], $registry->findByDefinition('blog'));
        static::assertSame([$otherAppRoute], $registry->findByDefinition('ce_blog'));
        static::assertSame([], $registry->findByDefinition('category'));
    }

    public function testGetSeoUrlRoutesMergesLoaderRoutes(): void
    {
        $compileTimeRoute = $this->createRoute('frontend.detail.page', 'blog');
        $appRoute = $this->createRoute('frontend.app.MyApp.blog-detail', 'ce_blog');

        $registry = new SeoUrlRouteRegistry([$compileTimeRoute], [new StaticSeoUrlRouteLoader([$appRoute])]);

        static::assertSame(
            [
                'frontend.detail.page' => $compileTimeRoute,
                'frontend.app.MyApp.blog-detail' => $appRoute,
            ],
            [...$registry->getSeoUrlRoutes()]
        );
    }

    public function testLoadersAreConsultedOnEveryCall(): void
    {
        $loader = new StaticSeoUrlRouteLoader([$this->createRoute('frontend.app.MyApp.blog-detail', 'ce_blog')]);

        $registry = new SeoUrlRouteRegistry([], [$loader]);

        $registry->findByRouteName('frontend.app.MyApp.blog-detail');
        $registry->findByDefinition('ce_blog');
        $registry->getSeoUrlRoutes();

        static::assertSame(3, $loader->loadCount);
    }

    public function testRoutesAddedToALoaderAfterTheRegistryWasBuiltAreFound(): void
    {
        $loader = new StaticSeoUrlRouteLoader([]);

        $registry = new SeoUrlRouteRegistry([], [$loader]);

        $appRoute = $this->createRoute('frontend.app.MyApp.blog-detail', 'ce_blog');
        $loader->routes = [$appRoute];

        static::assertSame($appRoute, $registry->findByRouteName('frontend.app.MyApp.blog-detail'));
        static::assertSame([$appRoute], $registry->findByDefinition('ce_blog'));
    }

    private function createRoute(string $routeName, string $entityName): SeoUrlRouteInterface
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $route = static::createStub(SeoUrlRouteInterface::class);
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($definition, $routeName, '{{ entity.id }}'));

        return $route;
    }
}

/**
 * @internal
 */
class StaticSeoUrlRouteLoader implements SeoUrlRouteLoaderInterface
{
    public int $loadCount = 0;

    /**
     * @param list<SeoUrlRouteInterface> $routes
     */
    public function __construct(public array $routes)
    {
    }

    public function load(): iterable
    {
        ++$this->loadCount;

        return $this->routes;
    }
}
