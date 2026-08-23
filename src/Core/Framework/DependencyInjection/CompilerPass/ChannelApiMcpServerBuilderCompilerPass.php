<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
class ChannelApiMcpServerBuilderCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mcp.channel_api.server.builder')) {
            return;
        }

        $this->registerCapabilitiesWithBuilder($container);
        $this->wireServiceLocator($container);
    }

    private function registerCapabilitiesWithBuilder(ContainerBuilder $container): void
    {
        $builderDef = $container->getDefinition('mcp.channel_api.server.builder');

        $this->registerTools($container, $builderDef);
        $this->registerPrompts($container, $builderDef);
        $this->registerResources($container, $builderDef);
    }

    private function registerTools(ContainerBuilder $container, Definition $builderDef): void
    {
        foreach (array_keys($container->findTaggedServiceIds('contena.channel_api_mcp.tool')) as $serviceId) {
            $class = $container->getDefinition($serviceId)->getClass() ?? $serviceId;
            $info = McpToolAttributeReader::resolveInfo($class, McpTool::class, ['name', 'title', 'description']);

            if ($info !== null) {
                $builderDef->addMethodCall('addTool', [$class, $info['name'], $info['title'], $info['description']]);
            }
        }
    }

    private function registerPrompts(ContainerBuilder $container, Definition $builderDef): void
    {
        foreach (array_keys($container->findTaggedServiceIds('contena.channel_api_mcp.prompt')) as $serviceId) {
            $class = $container->getDefinition($serviceId)->getClass() ?? $serviceId;
            $info = McpToolAttributeReader::resolveInfo($class, McpPrompt::class, ['name', 'title', 'description']);

            $builderDef->addMethodCall('addPrompt', [$class, $info ? $info['name'] : null, $info ? $info['title'] : null, $info ? $info['description'] : null]);
        }
    }

    private function registerResources(ContainerBuilder $container, Definition $builderDef): void
    {
        foreach (array_keys($container->findTaggedServiceIds('contena.channel_api_mcp.resource')) as $serviceId) {
            $class = $container->getDefinition($serviceId)->getClass() ?? $serviceId;
            $info = McpToolAttributeReader::resolveInfo($class, McpResource::class, ['uri', 'name', 'description', 'mimeType']);

            if ($info !== null) {
                $builderDef->addMethodCall('addResource', [$class, $info['uri'], $info['name'], null, $info['description'], $info['mimeType']]);
            }
        }
    }

    private function wireServiceLocator(ContainerBuilder $container): void
    {
        $allMcpServices = [];

        foreach (['contena.channel_api_mcp.tool', 'contena.channel_api_mcp.prompt', 'contena.channel_api_mcp.resource'] as $tag) {
            $allMcpServices = array_merge($allMcpServices, $container->findTaggedServiceIds($tag));
        }

        if ($allMcpServices === []) {
            return;
        }

        $serviceReferences = [];
        foreach (array_keys($allMcpServices) as $serviceId) {
            $serviceReferences[$serviceId] = new Reference($serviceId);
        }

        $container->getDefinition('mcp.channel_api.server.builder')->addMethodCall('setContainer', [
            ServiceLocatorTagPass::register($container, $serviceReferences),
        ]);
    }
}
