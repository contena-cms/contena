<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Mcp\Feature;

use Contena\Core\Framework\App\Feature\TranslatedString;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Contena\Core\Framework\App\Mcp\Feature\McpResourceFeatureDefinition;
use Contena\Core\Framework\Util\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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

    public function testExtractReturnsEmptyWhenNoMcpFile(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testExtractReadsResourcesFromMcpXml(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertCount(2, $configs);
        $config = $configs[0];
        static::assertSame('blog-stats', $config->name);
        static::assertSame('app-example://blog-stats', $config->uri);
        static::assertSame('https://app.example.com/mcp/resource/blog-stats', $config->url);
        static::assertSame('application/json', $config->mimeType);
        static::assertSame('Blog stats', $config->label->forLocale('en-GB'));
        static::assertSame('Live blog statistics', $config->description->forLocale('en-GB'));
    }

    public function testFromAppFillsMissingDefaultLocaleTranslationFromFallback(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'fr-FR',
        );

        static::assertCount(2, $configs);
        static::assertSame('Blog stats', $configs[0]->label->forLocale('fr-FR'));
        static::assertSame('Live blog statistics', $configs[0]->description->forLocale('fr-FR'));
    }

    public function testPayloadRoundTrip(): void
    {
        $declared = new McpResourceConfig(
            'blog-stats',
            'app-example://blog-stats',
            'https://app.example.com/mcp/resource/blog-stats',
            'application/json',
            new TranslatedString(['en-GB' => 'Blog stats']),
            new TranslatedString(['en-GB' => 'Live blog statistics']),
        );

        $hydrated = $this->definition->fromPayload($this->definition->toPayload($declared, null));

        static::assertEquals($declared, $hydrated);
    }
}
