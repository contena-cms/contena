<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(EntityRouteResolver::class)]
class EntityRouteResolverTest extends TestCase
{
    private SeoUrlPlaceholderHandlerInterface&MockObject $placeholderHandler;

    private RouterInterface&MockObject $router;

    protected function setUp(): void
    {
        $this->placeholderHandler = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $this->router = $this->createMock(RouterInterface::class);
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
            [$this->createSeoUrlRoute('blog', 'channel-api.blog.detail')],
        );

        static::assertSame('channel-api.blog.detail', $resolver->getRouteNameForEntityName('blog'));
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
            ->expects($this->once())
            ->method('generate')
            ->with(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => 'abc123'])
            ->willReturn('SEO_PLACEHOLDER');

        $resolver = $this->createResolverWithRoute('blog', BlogPageSeoUrlRoute::ROUTE_NAME, 'blogId');

        static::assertSame('SEO_PLACEHOLDER', $resolver->generateSeoUrlPlaceholder('blog', 'abc123'));
    }

    public function testGenerateUrlPassesResolvedRouteAndParameters(): void
    {
        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => 'abc123'])
            ->willReturn('/blog/some-blog/abc123');

        $resolver = $this->createResolverWithRoute('blog', BlogPageSeoUrlRoute::ROUTE_NAME, 'blogId');

        static::assertSame('/blog/some-blog/abc123', $resolver->generateUrl('blog', 'abc123'));
    }

    public function testGetSeoUrlRouteNameAndPathInfoSwapsRouteAndStripsBasePath(): void
    {
        $context = new RequestContext();
        $context->setBaseUrl('/subfolder');
        $this->router->method('getContext')->willReturn($context);
        $this->router->method('generate')->willReturn('/subfolder/channel-api/blog/abc123');

        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [$this->createEntitySeoUrlRoute('blog', 'channel-api.blog.detail', 'blogId')],
        );

        static::assertSame(
            ['routeName' => 'channel-api.blog.detail', 'pathInfo' => '/channel-api/blog/abc123'],
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
            [$this->createEntitySeoUrlRoute('blog', 'channel-api.blog.detail')],
        );

        static::assertSame([], $resolver->getSeoUrlRouteNameAndPathInfo(
            'blog',
            'channel-api.blog.detail',
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

    public function testFindEntitySeoUrlRouteReturnsMatchingChannelApiRoute(): void
    {
        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [
                $this->createEntitySeoUrlRoute('blog', 'channel-api.blog.detail'),
                $this->createEntitySeoUrlRoute('category', 'channel-api.category.detail'),
            ],
        );

        $route = $resolver->findEntitySeoUrlRoute('channel-api.category.detail');

        static::assertInstanceOf(EntitySeoUrlRouteInterface::class, $route);
        static::assertSame('channel-api.category.detail', $route->getConfig()->getRouteName());
    }

    public function testFindEntitySeoUrlRouteReturnsNullWhenNoRouteMatches(): void
    {
        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $this->placeholderHandler,
            $this->router,
            [$this->createEntitySeoUrlRoute('blog', 'channel-api.blog.detail')],
        );

        static::assertNull($resolver->findEntitySeoUrlRoute('channel-api.category.detail'));
    }

    public function testThrowsExceptionWhenRouteHasNoPrimaryKeyConfigured(): void
    {
        $this->expectExceptionObject(SeoUrlRouteConfigException::routeConfigMissingParameterKeyForPrimaryKey('blog'));

        $resolver = $this->createResolverWithRoute('blog', BlogPageSeoUrlRoute::ROUTE_NAME);

        $resolver->generateUrl('blog', 'abc123');
    }

    private function createResolverWithRoute(string $entityName, string $routeName, ?string $primaryKeyParameterKey = null): EntityRouteResolver
    {
        return new EntityRouteResolver(
            new SeoUrlRouteRegistry([$this->createSeoUrlRoute($entityName, $routeName, $primaryKeyParameterKey)]),
            $this->placeholderHandler,
            $this->router,
        );
    }

    private function createEntitySeoUrlRoute(string $entityName, string $routeName, ?string $primaryKeyParameterKey = null): EntitySeoUrlRouteInterface
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $route = static::createStub(EntitySeoUrlRouteInterface::class);
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($definition, $routeName, '{{ entity.name }}', true, $primaryKeyParameterKey));

        return $route;
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
