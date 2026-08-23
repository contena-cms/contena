<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Routing\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Contena\Core\Framework\Routing\Telemetry\DomainResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(DomainResolver::class)]
class DomainResolverTest extends TestCase
{
    /**
     * @param array<string, string> $attributes
     */
    #[DataProvider('routeProvider')]
    public function testResolve(string $route, array $attributes, string $expected): void
    {
        static::assertSame($expected, $this->createResolver()->resolve($this->createRequest($route, $attributes)));
    }

    public function testDoesNotMemoizeDynamicEntityRoutes(): void
    {
        $resolver = $this->createResolver();

        // clone/version routes share one fixed route name but carry a per-request `entity` param,
        // so they must be resolved fresh each call. Guards against a route-name memoization being extended
        // over these dynamic routes, which would pin every request to the first entity seen.
        static::assertSame('blog', $resolver->resolve($this->createRequest('api.clone', ['entity' => 'blog'])));
        static::assertSame('member', $resolver->resolve($this->createRequest('api.clone', ['entity' => 'member'])));
    }

    public static function routeProvider(): \Generator
    {
        // functional groups, first matching prefix wins
        yield 'frontend blog maps to blog' => ['frontend.blog.page', [], 'blog'];
        yield 'channel-api blog maps to blog' => ['channel-api.blog.list', [], 'blog'];
        yield 'navigation maps to category' => ['frontend.navigation.page', [], 'category'];
        yield 'landing page maps to content' => ['channel-api.landing-page.detail', [], 'content'];
        yield 'search maps to search' => ['frontend.search.page', [], 'search'];
        yield 'auth prefix precedes member catch-all' => ['frontend.account.login.page', [], 'auth'];
        yield 'account catch-all maps to member' => ['frontend.account.profile', [], 'member'];
        yield 'unmatched frontend route is other' => ['frontend.unknown.page', [], 'other'];

        // action domains (segment after api.action.)
        yield 'action cache maps to cache' => ['api.action.cache.index', [], 'cache'];
        yield 'action index maps to indexing' => ['api.action.index', [], 'indexing'];
        yield 'action system-config maps to core' => ['api.action.system-config', [], 'core'];
        yield 'unknown action segment is other' => ['api.action.unknown.thing', [], 'other'];
        yield 'sync action is not treated as action domain' => ['api.action.sync', [], 'other'];

        // admin CRUD: entity from the entityName attribute, hyphens normalised to underscores
        yield 'admin CRUD uses entityName attribute' => ['api.blog.detail', ['entityName' => 'blog'], 'blog'];
        yield 'admin CRUD normalises hyphenated resource name' => ['api.blog-main-category.detail', ['entityName' => 'blog-main-category'], 'category'];
        yield 'empty entityName falls through to other' => ['api.blog.detail', ['entityName' => ''], 'other'];

        // clone / version specials: entity from the entity attribute
        yield 'clone uses entity attribute' => ['api.clone', ['entity' => 'blog'], 'blog'];
        yield 'createVersion uses entity attribute' => ['api.createVersion', ['entity' => 'member'], 'member'];
        yield 'version special without entity is other' => ['api.deleteVersion', [], 'other'];

        // unmatched admin route
        yield 'unmatched api route is other' => ['api.something.else', [], 'other'];
    }

    private function createResolver(): DomainResolver
    {
        return new DomainResolver(new EntityGroupResolver());
    }

    /**
     * @param array<string, string> $attributes
     */
    private function createRequest(string $route, array $attributes = []): Request
    {
        $request = Request::create('/');
        $request->attributes->set('_route', $route);
        foreach ($attributes as $key => $value) {
            $request->attributes->set($key, $value);
        }

        return $request;
    }
}
