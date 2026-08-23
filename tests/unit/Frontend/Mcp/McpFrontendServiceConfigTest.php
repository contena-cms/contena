<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Mcp;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Mcp\Tool\ThemeConfigTool;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Guards that Frontend MCP services are correctly registered and tagged.
 *
 * The tag must be `mcp.tool` (not `contena.mcp.tool`). Non-Core bundle tools
 * are not processed by the Core MCP compiler pass, so they must use the SDK
 * tag directly. Wrong tags cause silent disappearance from the MCP registry.
 *
 * Discovery of the Frontend `Mcp` scan dir is covered by
 * {@see \Contena\Tests\DevOps\Core\Framework\Mcp\McpDiscoveryScanDirsConfigTest}.
 *
 * @internal
 */
#[CoversNothing]
class McpFrontendServiceConfigTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $loader = new PhpFileLoader($this->container, new FileLocator());
        $loader->load(\dirname(__DIR__, 4) . '/src/Frontend/DependencyInjection/mcp.php');
    }

    public function testThemeConfigToolIsRegistered(): void
    {
        static::assertTrue(
            $this->container->hasDefinition(ThemeConfigTool::class),
            'ThemeConfigTool is not registered in Frontend mcp.php',
        );
    }

    public function testThemeConfigToolIsTaggedWithMcpTool(): void
    {
        static::assertTrue(
            $this->container->getDefinition(ThemeConfigTool::class)->hasTag('mcp.tool'),
            'ThemeConfigTool must be tagged "mcp.tool" (not "contena.mcp.tool"). Non-Core bundle tools are not processed by the Core MCP compiler pass',
        );
    }
}
