<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Controller;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Page;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\Tool;
use Mcp\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\Controller\McpToolListController;
use Contena\Core\Framework\Mcp\McpCapabilityCatalog;

/**
 * @internal
 */
#[CoversClass(McpToolListController::class)]
#[CoversClass(McpCapabilityCatalog::class)]
class McpToolListControllerTest extends TestCase
{
    public function testListReturnsEmptyArrayWhenNoToolsRegistered(): void
    {
        $controller = $this->makeController(new Page([], null));
        $response = $controller->list();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame([], json_decode((string) $response->getContent(), true));
    }

    public function testListReturnsToolNameAndDescription(): void
    {
        $page = new Page([self::makeTool('contena-entity-search', 'Search entities')], null);
        $controller = $this->makeController($page);

        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertCount(1, $data);
        static::assertSame('contena-entity-search', $data[0]['name']);
        static::assertSame('Search entities', $data[0]['description']);
        static::assertSame('contena', $data[0]['group']);
    }

    public function testListSortsToolsAlphabetically(): void
    {
        $page = new Page([
            self::makeTool('contena-entity-upsert'),
            self::makeTool('contena-entity-search'),
            self::makeTool('contena-entity-delete'),
        ], null);

        $controller = $this->makeController($page);
        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertSame(
            ['contena-entity-delete', 'contena-entity-search', 'contena-entity-upsert'],
            array_column($data, 'name'),
        );
    }

    public function testListIncludesDependenciesFromConfig(): void
    {
        $page = new Page([self::makeTool('contena-entity-delete')], null);
        $controller = $this->makeController($page, [
            'contena-entity-delete' => ['contena-entity-search', 'contena-entity-schema'],
        ]);

        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertSame(
            ['contena-entity-search', 'contena-entity-schema'],
            $data[0]['dependencies'],
        );
    }

    public function testListDefaultsToEmptyDependenciesWhenToolNotConfigured(): void
    {
        $page = new Page([self::makeTool('contena-entity-schema')], null);
        $controller = $this->makeController($page);

        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertSame([], $data[0]['dependencies']);
    }

    public function testListIncludesPrivilegesFromCompileTimeConfig(): void
    {
        $page = new Page([self::makeTool('contena-entity-delete')], null);
        $privileges = ['static' => ['product:read'], 'entityParam' => null, 'operations' => ['update']];
        $controller = $this->makeController($page, [], ['contena-entity-delete' => $privileges]);

        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertSame(['product:read'], $data[0]['requiredPrivileges']['static']);
        static::assertNull($data[0]['requiredPrivileges']['entityParam']);
        static::assertSame(['update'], $data[0]['requiredPrivileges']['operations']);
    }

    public function testCapabilitiesReturnsAllCapabilityTypes(): void
    {
        $toolsPage = new Page([self::makeTool('contena-entity-search', 'Search')], null);
        $resourcesPage = new Page([
            new ResourceDefinition('contena://entities', 'entities', null, 'All entities', null, null, null),
        ], null);
        $promptsPage = new Page([
            new Prompt('contena-context', null, 'Context prompt', []),
        ], null);

        $controller = $this->makeController($toolsPage, [], [], $resourcesPage, $promptsPage);
        $response = $controller->capabilities();

        static::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);

        static::assertArrayHasKey('tools', $data);
        static::assertArrayHasKey('resources', $data);
        static::assertArrayHasKey('prompts', $data);
        static::assertSame('contena-entity-search', $data['tools'][0]['name']);
        static::assertSame('contena://entities', $data['resources'][0]['uri']);
        static::assertSame('contena-context', $data['prompts'][0]['name']);
    }

    public function testListIncludesTitleWhenSet(): void
    {
        $page = new Page([
            new Tool(
                name: 'contena-entity-search',
                title: 'Entity Search',
                inputSchema: ['type' => 'object', 'properties' => [], 'required' => null],
                description: 'Search entities',
                annotations: null,
            ),
        ], null);
        $controller = $this->makeController($page);

        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertSame('Entity Search', $data[0]['title']);
    }

    public function testListHandlesNullDescription(): void
    {
        $page = new Page([self::makeTool('contena-entity-schema', null)], null);
        $controller = $this->makeController($page);

        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertNull($data[0]['description']);
    }

    private static function makeTool(string $name, ?string $description = null): Tool
    {
        return new Tool(
            name: $name,
            title: null,
            inputSchema: ['type' => 'object', 'properties' => [], 'required' => null],
            description: $description,
            annotations: null,
        );
    }

    /**
     * @param array<string, list<string>> $toolDependencies
     * @param array<string, array{static: list<string>, entityParam: ?string, operations: list<string>}> $toolPrivileges
     */
    private function makeController(
        Page $page,
        array $toolDependencies = [],
        array $toolPrivileges = [],
        ?Page $resourcesPage = null,
        ?Page $promptsPage = null,
    ): McpToolListController {
        $registry = static::createStub(RegistryInterface::class);
        $registry->method('getTools')->willReturn($page);
        $registry->method('getResources')->willReturn($resourcesPage ?? new Page([], null));
        $registry->method('getPrompts')->willReturn($promptsPage ?? new Page([], null));

        $catalog = new McpCapabilityCatalog($registry, $toolDependencies, $toolPrivileges);

        return new McpToolListController(Server::builder(), $catalog);
    }
}
