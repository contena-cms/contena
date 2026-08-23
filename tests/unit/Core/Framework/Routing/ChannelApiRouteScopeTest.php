<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ChannelApiRouteScope::class)]
class ChannelApiRouteScopeTest extends TestCase
{
    private ChannelApiRouteScope $scope;

    protected function setUp(): void
    {
        $this->scope = new ChannelApiRouteScope();
    }

    #[DataProvider('allowedPathsProvider')]
    public function testIsAllowedPath(string $path, bool $expected): void
    {
        static::assertSame($expected, $this->scope->isAllowedPath($path));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('allowedContextsProvider')]
    public function testIsAllowed(array $attributes, bool $expected): void
    {
        static::assertSame($expected, $this->scope->isAllowed(new Request(attributes: $attributes)));
    }

    public function testIsAllowedRequiresContext(): void
    {
        $this->expectExceptionObject(RoutingException::missingRouteAttribute(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, 'channel-api.info'));

        $this->scope->isAllowed(new Request(attributes: ['_route' => 'channel-api.info', 'auth_required' => true]));
    }

    public static function allowedPathsProvider(): \Generator
    {
        yield 'channel api path' => ['/channel-api/context', true];
        yield 'admin api path' => ['/api/_info', false];
    }

    public static function allowedContextsProvider(): \Generator
    {
        yield 'channel source' => [
            [PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => new Context(new ChannelApiSource('channel-id')), 'auth_required' => true],
            true,
        ];
        yield 'system source without auth' => [
            ['auth_required' => false],
            true,
        ];
        yield 'missing channel source with auth' => [
            [PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => Context::createDefaultContext(), 'auth_required' => true],
            false,
        ];
    }
}
