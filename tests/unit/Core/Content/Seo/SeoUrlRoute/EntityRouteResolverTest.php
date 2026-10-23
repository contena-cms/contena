<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteLoaderInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(EntityRouteResolver::class)]
class EntityRouteResolverTest extends TestCase
{
    private SeoUrlPlaceholderHandlerInterface&Stub $placeholderHandler;

    private RouterInterface&Stub $router;

    protected function setUp(): void
    {
        $this->placeholderHandler = static::createStub(SeoUrlPlaceholderHandlerInterface::class);
        $this->router = static::createStub(RouterInterface::class);
    }

    public function testGetRouteNameReturnsRegisteredRoute(): void
    {
        $resolver = $this->createResolverWithRoute('blog', BlogPageSeoUrlRoute::ROUTE_NAME);

        static::assertSame(BlogPageSeoUrlRoute::ROUTE_NAME, $resolver->getRouteNameForEntityName('blog'));
    }

    public function testGetRouteNameResolvesViaConfiguredRouteWhenNotRegistered(): void
    {
        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [$this->createSeoUrlRoute('blog', 'store-api.blog.detail')],
        );

        static::assertSame('store-api.blog.detail', $resolver->getRouteNameForEntityName('blog'));
    }

    public function testGetRouteNameThrowsWhenEntityHasNoRoute(): void
    {
        $resolver = new EntityRouteResolver(new SeoUrlRouteRegistry([]), $this->placeholderHandler, $this->router);

        $this->expectExceptionObject(SeoUrlRouteConfigException::routeConfigNotFoundForEntityName('blog'));

        $resolver->getRouteNameForEntityName('blog');
    }

    public function testGenerateSeoUrlPlaceholderPassesResolvedRouteAndParameters(): void
    {
        $this->placeholderHandler
            ->method('generate')
            ->willReturn('SEO_PLACEHOLDER');

        $resolver = $this->createResolverWithRoute('blog', BlogPageSeoUrlRoute::ROUTE_NAME, 'blogId');

        static::assertSame('SEO_PLACEHOLDER', $resolver->generateSeoUrlPlaceholder('blog', 'abc123'));
    }

    public function testGenerateUrlPassesResolvedRouteAndParameters(): void
    {
        $this->router
            ->method('generate')
            ->willReturn('/blog/some-blog/abc123');

        $resolver = $this->createResolverWithRoute('blog', BlogPageSeoUrlRoute::ROUTE_NAME, 'blogId');

        static::assertSame('/blog/some-blog/abc123', $resolver->generateUrl('blog', 'abc123'));
    }

    public function testGenerateSeoUrlPlaceholderUsesTheTargetRouteAndMergedParameters(): void
    {
        $capturedArguments = null;
        $this->placeholderHandler
            ->method('generate')
            ->willReturnCallback(static function (string $name, array $parameters) use (&$capturedArguments): string {
                $capturedArguments = [$name, $parameters];

                return 'SEO_PLACEHOLDER';
            });

        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([$this->createAppSeoUrlRoute()]),
            $this->placeholderHandler,
            $this->router,
        );

        static::assertSame('SEO_PLACEHOLDER', $resolver->generateSeoUrlPlaceholder('ce_blog', 'abc123'));
        static::assertSame(
            ['frontend.script_endpoint', ['hook' => 'blog-detail', 'id' => 'abc123']],
            $capturedArguments
        );
    }

    public function testGenerateUrlUsesTheTargetRouteAndMergedParameters(): void
    {
        $capturedArguments = null;
        $this->router
            ->method('generate')
            ->willReturnCallback(static function (string $name, array $parameters) use (&$capturedArguments): string {
                $capturedArguments = [$name, $parameters];

                return '/frontend/script/blog-detail?id=abc123';
            });

        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([$this->createAppSeoUrlRoute()]),
            $this->placeholderHandler,
            $this->router,
        );

        static::assertSame('/frontend/script/blog-detail?id=abc123', $resolver->generateUrl('ce_blog', 'abc123'));
        static::assertSame(
            ['frontend.script_endpoint', ['hook' => 'blog-detail', 'id' => 'abc123']],
            $capturedArguments
        );
    }

    public function testGetSeoUrlRouteNameAndPathInfoSwapsRouteAndStripsBasePath(): void
    {
        $context = new RequestContext();
        $context->setBaseUrl('/subfolder');
        $this->router->method('getContext')->willReturn($context);
        $this->router->method('generate')->willReturn('/subfolder/store-api/blog/abc123');

        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [$this->createEntitySeoUrlRoute('blog', 'store-api.blog.detail', 'blogId')],
        );

        static::assertSame(
            ['routeName' => 'store-api.blog.detail', 'pathInfo' => '/store-api/blog/abc123'],
            $resolver->getSeoUrlRouteNameAndPathInfo(
                'blog',
                BlogPageSeoUrlRoute::ROUTE_NAME,
                'abc123',
                Defaults::CHANNEL_TYPE_API,
            ),
        );
    }

    public function testGetSeoUrlRouteNameAndPathInfoReturnsEmptyWhenRouteAlreadyMatches(): void
    {
        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [$this->createEntitySeoUrlRoute('blog', 'store-api.blog.detail')],
        );

        static::assertSame([], $resolver->getSeoUrlRouteNameAndPathInfo(
            'blog',
            'store-api.blog.detail',
            'abc123',
            Defaults::CHANNEL_TYPE_API,
        ));
    }

    public function testGetSeoUrlRouteNameAndPathInfoReturnsEmptyForAnotherFrontendRouteOfTheEntity(): void
    {
        $loader = static::createStub(SeoUrlRouteLoaderInterface::class);
        $loader->method('load')->willReturn([$this->createSeoUrlRoute('blog', 'frontend.app.MyApp.blog-detail', 'id')]);

        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([$this->createSeoUrlRoute('blog', BlogPageSeoUrlRoute::ROUTE_NAME, 'blogId')], [$loader]),
            $this->placeholderHandler,
            $this->router,
        );

        static::assertSame([], $resolver->getSeoUrlRouteNameAndPathInfo(
            'blog',
            'frontend.app.MyApp.blog-detail',
            'abc123',
            Defaults::CHANNEL_TYPE_FRONTEND,
        ));
    }

    public function testGetSeoUrlRouteNameAndPathInfoReturnsEmptyForAnotherStoreApiRouteOfTheEntity(): void
    {
        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [
                $this->createEntitySeoUrlRoute('blog', 'store-api.blog.detail', 'blogId'),
                $this->createEntitySeoUrlRoute('blog', 'store-api.plugin.blog-detail', 'blogId'),
            ],
        );

        static::assertSame([], $resolver->getSeoUrlRouteNameAndPathInfo(
            'blog',
            'store-api.plugin.blog-detail',
            'abc123',
            Defaults::CHANNEL_TYPE_API,
        ));
    }

    public function testGetSeoUrlRouteNameAndPathInfoReturnsEmptyWhenEntityHasNoRoute(): void
    {
        $resolver = new EntityRouteResolver(new SeoUrlRouteRegistry([]), $this->placeholderHandler, $this->router);

        static::assertSame([], $resolver->getSeoUrlRouteNameAndPathInfo(
            'blog',
            BlogPageSeoUrlRoute::ROUTE_NAME,
            'abc123',
            Defaults::CHANNEL_TYPE_API,
        ));
    }

    public function testThrowsExceptionWhenRouteHasNoPrimaryKeyConfigured(): void
    {
        $this->expectExceptionObject(SeoUrlRouteConfigException::routeConfigMissingParameterKeyForPrimaryKey('blog'));

        $resolver = $this->createResolverWithRoute('blog', BlogPageSeoUrlRoute::ROUTE_NAME);

        $resolver->generateUrl('blog', 'abc123');
    }

    public function testFindEntitySeoUrlRouteReturnsMatchingStoreApiRoute(): void
    {
        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [
                $this->createEntitySeoUrlRoute('blog', 'store-api.blog.detail'),
                $this->createEntitySeoUrlRoute('category', 'store-api.category.detail'),
            ],
        );

        $route = $resolver->findEntitySeoUrlRoute('store-api.category.detail');

        static::assertInstanceOf(EntitySeoUrlRouteInterface::class, $route);
        static::assertSame('store-api.category.detail', $route->getConfig()->getRouteName());
    }

    public function testFindEntitySeoUrlRouteReturnsNullWhenNoRouteMatches(): void
    {
        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [$this->createEntitySeoUrlRoute('blog', 'store-api.blog.detail')],
        );

        static::assertNull($resolver->findEntitySeoUrlRoute('store-api.category.detail'));
    }

    private function createAppSeoUrlRoute(): SeoUrlRouteInterface
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('ce_blog');

        $config = new SeoUrlRouteConfig(
            definition: $definition,
            routeName: 'frontend.app.MyApp.blog-detail',
            template: '{{ ceBlog.translated.title }}',
            primaryKeyParameterKey: 'id',
            targetRouteName: 'frontend.script_endpoint',
            routeParameters: ['hook' => 'blog-detail'],
        );

        $seoUrlRoute = static::createStub(SeoUrlRouteInterface::class);
        $seoUrlRoute->method('getConfig')->willReturn($config);

        return $seoUrlRoute;
    }

    private function createEntitySeoUrlRoute(string $entityName, string $routeName, ?string $primaryKeyParameterKey = null): EntitySeoUrlRouteInterface
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $route = static::createStub(EntitySeoUrlRouteInterface::class);
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($definition, $routeName, '{{ entity.name }}', true, $primaryKeyParameterKey));

        return $route;
    }

    private function createResolverWithRoute(string $entityName, string $routeName, ?string $primaryKeyParameterKey = null): EntityRouteResolver
    {
        return new EntityRouteResolver(
            new SeoUrlRouteRegistry([$this->createSeoUrlRoute($entityName, $routeName, $primaryKeyParameterKey)]),
            $this->placeholderHandler,
            $this->router,
        );
    }

    private function createSeoUrlRoute(string $entityName, string $routeName, ?string $primaryKeyParameterKey = null): SeoUrlRouteInterface
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $config = new SeoUrlRouteConfig($definition, $routeName, '{{ entity.name }}', true, $primaryKeyParameterKey);

        $seoUrlRoute = static::createStub(SeoUrlRouteInterface::class);
        $seoUrlRoute->method('getConfig')->willReturn($config);

        return $seoUrlRoute;
    }
}
