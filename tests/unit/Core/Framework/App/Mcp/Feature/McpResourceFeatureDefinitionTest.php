<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Mcp\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Feature\TranslatedString;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Contena\Core\Framework\App\Mcp\Feature\McpResourceFeatureDefinition;
use Contena\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[CoversClass(McpResourceFeatureDefinition::class)]
class McpResourceFeatureDefinitionTest extends TestCase
{
    private McpResourceFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new McpResourceFeatureDefinition();
    }

    public function testType(): void
    {
        static::assertSame('mcp_resource', $this->definition->getType());
        static::assertSame(McpResourceConfig::class, $this->definition->getConfigClass());
    }

    public function testAnAppWithoutMcpXmlDeclaresNothing(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testResourcesAreReadFromMcpXml(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertEquals([
            new McpResourceConfig(
                'order-stats',
                'app-example://order-stats',
                'https://app.example.com/mcp/resource/order-stats',
                'application/json',
                new TranslatedString(['en-GB' => 'Order Stats']),
                new TranslatedString(['en-GB' => 'Live order statistics']),
            ),
            new McpResourceConfig(
                'blog-list',
                'app-example://blog-list',
                'https://app.example.com/mcp/resource/blog-list',
                null,
                new TranslatedString(['en-GB' => 'Blog List']),
                new TranslatedString([]),
            ),
        ], $configs);
    }

    public function testAStoredResourceIsReplacedByTheDeclaredOne(): void
    {
        $declared = new McpResourceConfig(
            'order-stats',
            'app-example://order-stats',
            'https://app.example.com/mcp/resource/order-stats',
            'application/json',
            new TranslatedString(['en-GB' => 'Order Stats']),
            new TranslatedString(['en-GB' => 'Live order statistics']),
        );

        $stored = new McpResourceConfig('order-stats', 'app-example://stale', 'https://stale.example.com', null, new TranslatedString(['en-GB' => 'Old']), new TranslatedString([]));

        $hydrated = $this->definition->fromPayload($this->definition->toPayload($declared, $stored));

        static::assertEquals($declared, $hydrated);
    }
}
