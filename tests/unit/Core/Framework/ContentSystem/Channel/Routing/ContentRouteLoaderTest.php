<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Channel\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Channel\Routing\ContentRouteDefinition;
use Contena\Core\Framework\ContentSystem\Channel\Routing\ContentRouteLoader;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ContentRouteLoader::class)]
class ContentRouteLoaderTest extends TestCase
{
    #[TestDox('creates route collection with routes from definitions')]
    public function testLoadCreatesRouteCollectionFromDefinitions(): void
    {
        $definition = new ContentRouteDefinition(
            path: '/channel-api/content/{path}',
            name: 'channel-api.content.main',
            requirements: ['path' => '.+'],
            defaults: ['_controller' => 'ContentRoute::load'],
        );

        $loader = new ContentRouteLoader([$definition]);

        $result = $loader->load(null);

        static::assertCount(1, $result);

        $route = $result->get('channel-api.content.main');
        static::assertNotNull($route);
        static::assertSame('/channel-api/content/{path}', $route->getPath());
        static::assertSame([Request::METHOD_GET], $route->getMethods());
        static::assertSame(['path' => '.+'], $route->getRequirements());
        static::assertSame('ContentRoute::load', $route->getDefault('_controller'));
    }

    #[TestDox('creates empty route collection when no definitions provided')]
    public function testLoadWithNoDefinitionsCreatesEmptyRouteCollection(): void
    {
        $loader = new ContentRouteLoader([]);

        $result = $loader->load(null);

        static::assertCount(0, $result);
    }

    #[TestDox('throws exception when routes are already loaded')]
    public function testLoadThrowsWhenAlreadyLoaded(): void
    {
        $loader = new ContentRouteLoader([]);
        $loader->load(null);

        $this->expectExceptionObject(ContentSystemException::routesAlreadyLoaded());

        $loader->load(null);
    }

    #[TestDox('returns true for content_system type')]
    public function testSupportsReturnsTrueForContentSystemType(): void
    {
        $loader = new ContentRouteLoader([]);

        static::assertTrue($loader->supports(null, 'content_system'));
    }

    #[TestDox('returns false for unsupported types')]
    public function testSupportsReturnsFalseForOtherTypes(): void
    {
        $loader = new ContentRouteLoader([]);

        static::assertFalse($loader->supports(null, 'xml'));
    }
}
