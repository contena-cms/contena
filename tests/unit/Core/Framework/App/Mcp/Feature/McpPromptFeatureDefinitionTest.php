<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Mcp\Feature;

use Contena\Core\Framework\App\Feature\TranslatedString;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Contena\Core\Framework\App\Mcp\Feature\McpPromptFeatureDefinition;
use Contena\Core\Framework\Util\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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

    public function testExtractReturnsEmptyWhenNoMcpFile(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testExtractReadsPromptsFromMcpXml(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertCount(2, $configs);
        $config = $configs[0];
        static::assertSame('blog-context', $config->name);
        static::assertSame('https://app.example.com/mcp/prompt/blog-context', $config->url);
        static::assertSame('Blog context', $config->label->forLocale('en-GB'));
        static::assertSame('Context for blog management', $config->description->forLocale('en-GB'));
    }

    public function testFromAppFillsMissingDefaultLocaleTranslationFromFallback(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'fr-FR',
        );

        static::assertCount(2, $configs);
        static::assertSame('Blog context', $configs[0]->label->forLocale('fr-FR'));
        static::assertSame('Context for blog management', $configs[0]->description->forLocale('fr-FR'));
    }

    public function testPayloadRoundTrip(): void
    {
        $declared = new McpPromptConfig(
            'blog-context',
            'https://app.example.com/mcp/prompt/blog-context',
            new TranslatedString(['en-GB' => 'Blog context']),
            new TranslatedString(['en-GB' => 'Context for blog management']),
        );

        $hydrated = $this->definition->fromPayload($this->definition->toPayload($declared, null));

        static::assertEquals($declared, $hydrated);
    }
}
