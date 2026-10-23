<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Mcp\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Feature\TranslatedString;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Contena\Core\Framework\App\Mcp\Feature\McpPromptFeatureDefinition;
use Contena\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[CoversClass(McpPromptFeatureDefinition::class)]
class McpPromptFeatureDefinitionTest extends TestCase
{
    private McpPromptFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new McpPromptFeatureDefinition();
    }

    public function testType(): void
    {
        static::assertSame('mcp_prompt', $this->definition->getType());
        static::assertSame(McpPromptConfig::class, $this->definition->getConfigClass());
    }

    public function testAnAppWithoutMcpXmlDeclaresNothing(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testPromptsAreReadFromMcpXml(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertEquals([
            new McpPromptConfig(
                'order-context',
                'https://app.example.com/mcp/prompt/order-context',
                new TranslatedString(['en-GB' => 'Order Context']),
                new TranslatedString(['en-GB' => 'Context for orders']),
            ),
            new McpPromptConfig(
                'blog-context',
                'https://app.example.com/mcp/prompt/blog-context',
                new TranslatedString(['en-GB' => 'Blog Context']),
                new TranslatedString([]),
            ),
        ], $configs);
    }

    public function testAStoredPromptIsReplacedByTheDeclaredOne(): void
    {
        $declared = new McpPromptConfig(
            'order-context',
            'https://app.example.com/mcp/prompt/order-context',
            new TranslatedString(['en-GB' => 'Order Context']),
            new TranslatedString(['en-GB' => 'Context for orders']),
        );

        $stored = new McpPromptConfig('order-context', 'https://stale.example.com', new TranslatedString(['en-GB' => 'Old']), new TranslatedString([]));

        $hydrated = $this->definition->fromPayload($this->definition->toPayload($declared, $stored));

        static::assertEquals($declared, $hydrated);
    }
}
