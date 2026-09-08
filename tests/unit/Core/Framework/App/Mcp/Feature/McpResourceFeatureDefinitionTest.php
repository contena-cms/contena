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

        static::assertCount(1, $configs);
        $config = $configs[0];
        static::assertSame('order-stats', $config->name);
        static::assertSame('app-example://order-stats', $config->uri);
        static::assertSame('https://app.example.com/mcp/resource/order-stats', $config->url);
        static::assertSame('application/json', $config->mimeType);
        static::assertSame('Order Stats', $config->label->forLocale('en-GB'));
        static::assertSame('Live order statistics', $config->description->forLocale('en-GB'));
    }

    public function testFromAppFillsMissingDefaultLocaleTranslationFromFallback(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'fr-FR',
        );

        static::assertCount(1, $configs);
        static::assertSame('Order Stats', $configs[0]->label->forLocale('fr-FR'));
        static::assertSame('Live order statistics', $configs[0]->description->forLocale('fr-FR'));
    }

    public function testPayloadRoundTrip(): void
    {
        $declared = new McpResourceConfig(
            'order-stats',
            'app-example://order-stats',
            'https://app.example.com/mcp/resource/order-stats',
            'application/json',
            new TranslatedString(['en-GB' => 'Order Stats']),
            new TranslatedString(['en-GB' => 'Live order statistics']),
        );

        $hydrated = $this->definition->fromPayload($this->definition->toPayload($declared, null));

        static::assertEquals($declared, $hydrated);
    }
}
