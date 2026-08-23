<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\DependencyInjection\DependencyInjectionException;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\McpToolsetRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * First MCP compiler pass: remaps Contena-specific tags to MCP SDK tags, enforces the
 * configured tool allowlist, and detects duplicate tool name conflicts.
 *
 * Must run before McpToolAnalysisCompilerPass and McpServerBuilderCompilerPass.
 */
class McpToolDiscoveryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach (['contena.mcp.', 'contena.channel_api_mcp.'] as $paramPrefix) {
            $container->setParameter($paramPrefix . 'tool_dependencies', []);
            $container->setParameter($paramPrefix . 'tool_privileges', []);
            $container->setParameter($paramPrefix . 'advertised_tools', []);
            $container->setParameter($paramPrefix . 'tool_groups', []);
        }

        if (!$container->hasDefinition('mcp.server.builder')) {
            return;
        }

        $tagMapping = [
            'contena.mcp.tool' => 'mcp.tool',
            'contena.mcp.prompt' => 'mcp.prompt',
            'contena.mcp.resource' => 'mcp.resource',
        ];

        foreach ($tagMapping as $contenaTag => $mcpTag) {
            foreach ($container->findTaggedServiceIds($contenaTag) as $serviceId => $tags) {
                $definition = $container->getDefinition($serviceId);

                if (!$definition->hasTag($mcpTag)) {
                    $definition->addTag($mcpTag);
                }
            }
        }

        $this->enforceToolAllowlist($container);

        // Per scope: names are unique within a scope's own registry.
        foreach (['mcp.tool' => 'contena.mcp.advertised_tools', 'contena.channel_api_mcp.tool' => 'contena.channel_api_mcp.advertised_tools'] as $tag => $advertisedParam) {
            $this->detectToolNameConflicts($container, $tag);
            $this->buildAdvertisedTools($container, $tag, $advertisedParam);
        }
    }

    /**
     * When contena.mcp.allowed_tools is non-empty, remove any tool services
     * whose name is not in the allowlist.
     */
    private function enforceToolAllowlist(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('contena.mcp.allowed_tools')) {
            return;
        }

        /** @var list<string> $allowedTools */
        $allowedTools = $container->getParameter('contena.mcp.allowed_tools');

        if ($allowedTools === []) {
            return;
        }

        foreach ($container->findTaggedServiceIds('mcp.tool') as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $toolInfo = McpToolAttributeReader::resolveInfo($class, McpTool::class, ['name', 'description']);

            if ($toolInfo === null || !\in_array($toolInfo['name'], $allowedTools, true)) {
                $container->removeDefinition($serviceId);
            }
        }
    }

    private function detectToolNameConflicts(ContainerBuilder $container, string $tag): void
    {
        /** @var array<string, string> $toolNames tool-name => service-id */
        $toolNames = [];

        foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $toolInfo = McpToolAttributeReader::resolveInfo($class, McpTool::class, ['name', 'description']);

            if ($toolInfo === null || $toolInfo['name'] === null) {
                continue;
            }

            if (isset($toolNames[$toolInfo['name']])) {
                throw DependencyInjectionException::duplicateMcpToolName($toolInfo['name'], $toolNames[$toolInfo['name']], $serviceId);
            }

            $toolNames[$toolInfo['name']] = $serviceId;
        }
    }

    /**
     * The initial tools/list surface is exactly the discovery group (tool-search + toolsets-list/
     * -enable). Every other tool is deferred and only advertised once its toolset is enabled, so a
     * domain tool cannot leak into the default surface — group membership is the single gate.
     */
    private function buildAdvertisedTools(ContainerBuilder $container, string $tag, string $advertisedParam): void
    {
        $advertisedTools = [];

        foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $toolInfo = McpToolAttributeReader::resolveInfo($class, McpTool::class, ['name']);

            if ($toolInfo === null || !\is_string($toolInfo['name'] ?? null) || !class_exists($class)) {
                continue;
            }

            $groupInfo = McpToolAttributeReader::resolveInfo($class, McpToolGroup::class, ['group']);

            if ($groupInfo !== null && ($groupInfo['group'] ?? null) === McpToolsetRegistry::DISCOVERY_GROUP) {
                $advertisedTools[] = $toolInfo['name'];
            }
        }

        $container->setParameter($advertisedParam, array_values(array_unique($advertisedTools)));
    }
}
