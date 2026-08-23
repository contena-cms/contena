<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Third MCP compiler pass: registers plugin capabilities with the MCP server builder and
 * wires the discovery cache so file scanning only happens once per container warm-up.
 *
 * Must run after McpToolDiscoveryCompilerPass and McpToolAnalysisCompilerPass.
 */
class McpServerBuilderCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mcp.server.builder')) {
            return;
        }

        $this->registerPluginCapabilitiesWithBuilder($container);
        $this->enableDiscoveryCache($container);
    }

    /**
     * Registers plugin capabilities (tagged contena.mcp.*) with the MCP server builder via
     * addTool/addPrompt/addResource so they appear in the HTTP endpoint regardless of scan_dirs.
     * Core capabilities live inside scan_dirs and are registered by DiscoveryLoader instead.
     */
    private function registerPluginCapabilitiesWithBuilder(ContainerBuilder $container): void
    {
        $builderDef = $container->getDefinition('mcp.server.builder');

        foreach ($container->findTaggedServiceIds('contena.mcp.tool') as $serviceId => $tags) {
            if (!$container->hasDefinition($serviceId)) {
                continue; // @codeCoverageIgnore
            }

            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $toolInfo = McpToolAttributeReader::resolveInfo($class, McpTool::class, ['name', 'title', 'description']);

            if ($toolInfo !== null) {
                $builderDef->addMethodCall('addTool', [$class, $toolInfo['name'], $toolInfo['title'], $toolInfo['description']]);
            }
        }

        foreach ($container->findTaggedServiceIds('contena.mcp.prompt') as $serviceId => $tags) {
            if (!$container->hasDefinition($serviceId)) {
                continue; // @codeCoverageIgnore
            }

            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $promptInfo = McpToolAttributeReader::resolveInfo($class, McpPrompt::class, ['name', 'title', 'description']);

            $builderDef->addMethodCall('addPrompt', [$class, $promptInfo ? $promptInfo['name'] : null, $promptInfo ? $promptInfo['title'] : null, $promptInfo ? $promptInfo['description'] : null]);
        }

        foreach ($container->findTaggedServiceIds('contena.mcp.resource') as $serviceId => $tags) {
            if (!$container->hasDefinition($serviceId)) {
                continue; // @codeCoverageIgnore
            }

            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $resourceInfo = McpToolAttributeReader::resolveInfo($class, McpResource::class, ['uri', 'name', 'description', 'mimeType']);

            if ($resourceInfo !== null) {
                $builderDef->addMethodCall('addResource', [$class, $resourceInfo['uri'], $resourceInfo['name'], null, $resourceInfo['description'], $resourceInfo['mimeType']]);
            }
        }
    }

    /**
     * Adds a PSR-16 cache to the MCP SDK's discovery process so file scanning
     * and reflection are only performed once instead of on every request.
     */
    private function enableDiscoveryCache(ContainerBuilder $container): void
    {
        $builderDef = $container->getDefinition('mcp.server.builder');

        if (!$container->hasDefinition('contena.mcp.discovery_cache')) {
            return;
        }

        foreach ($builderDef->getMethodCalls() as $index => [$method, $args]) {
            if ($method !== 'setDiscovery') {
                continue;
            }

            $args[3] = new Reference('contena.mcp.discovery_cache');
            $builderDef->removeMethodCall($method);
            $builderDef->addMethodCall($method, $args);

            break;
        }
    }
}
