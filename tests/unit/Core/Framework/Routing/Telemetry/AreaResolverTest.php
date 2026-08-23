<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Routing\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Routing\Telemetry\AreaResolver;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AreaResolver::class)]
class AreaResolverTest extends TestCase
{
    /**
     * @param list<string> $scopes
     */
    #[DataProvider('requestProvider')]
    public function testResolve(string $route, array $scopes, string $expected): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', $route);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, $scopes);

        static::assertSame($expected, new AreaResolver()->resolve($request));
    }

    public function testResolveDefaultsToOtherWithoutRouteOrScope(): void
    {
        static::assertSame('other', new AreaResolver()->resolve(Request::create('/')));
    }

    public static function requestProvider(): \Generator
    {
        yield 'sync route is special-cased regardless of scope' => ['api.action.sync', [ApiRouteScope::ID], 'sync-api'];

        yield 'channel-api scope' => ['channel-api.blog.search', [ChannelApiRouteScope::ID], 'channel-api'];
        yield 'admin-api scope' => ['api.blog.search', [ApiRouteScope::ID], 'admin-api'];
        yield 'frontend scope' => ['frontend.blog.page', ['frontend'], 'frontend'];
        yield 'administration scope' => ['administration.index', ['administration'], 'administration'];
        yield 'admin dashboard route with administration scope' => ['api.admin.dashboard', ['administration'], 'administration'];
        yield 'unknown scope is other' => ['some.route', ['unknown'], 'other'];

        // channel-api takes precedence when several scopes are present
        yield 'channel-api scope precedes admin-api' => ['mixed.route', [ApiRouteScope::ID, ChannelApiRouteScope::ID], 'channel-api'];
    }
}
