<?php declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/** @codeCoverageIgnore */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    if (!$builder->hasExtension('mcp')) {
        return;
    }

    // Contena exposes two MCP servers, and the bundle assigns an element to a server by matching
    // the patterns below against its service id and class. Core and in-tree bundle capabilities live
    // in stable namespaces, so a prefix per server keeps the two endpoints disjoint.
    //
    // Plugin and third-party bundle capabilities cannot be expressed here: their namespace is
    // arbitrary, and the wildcard is not an option because it would match the other server's
    // elements too. McpToolDiscoveryCompilerPass appends those class names to the
    // "mcp.servers.elements" parameter instead, which is the channel the bundle's own compiler pass
    // reads its per-server element lists from.
    //
    // A pattern that matches nothing is a fatal error in the bundle, so the Frontend prefix is only
    // added when that bundle is actually installed. It can still be emptied out later -- the
    // namespace holds exactly one tool, so `contena.mcp.allowed_tools` without `contena-theme-config`
    // orphans the prefix. McpToolDiscoveryCompilerPass::pruneUnmatchedPatterns() drops it again in
    // that case, so hiding a tool never aborts the container build.
    $bundles = $builder->getParameter('kernel.bundles');
    \assert(\is_array($bundles));

    $adminRegistry = ['Contena\\Core\\Framework\\Mcp\\'];
    if (isset($bundles['Frontend'])) {
        $adminRegistry[] = 'Contena\\Frontend\\Mcp\\';
    }

    $container->extension('mcp', [
        'servers' => [
            'admin' => [
                'name' => 'Contena',
                'version' => '1.0.0',
                'description' => 'Contena MCP server providing tools for entity management, system configuration, and frontend operations.',
                'instructions' => "This MCP server exposes Contena content-management platform capabilities.\nUse entity tools to search, read, and manage CMS data.\nThe advertised tool list is not the full catalogue. If no advertised tool matches the requested action, call contena-tool-search first instead of assuming the action is unsupported; use contena-toolsets-list and contena-toolset-enable to make a matched tool callable if your client cannot invoke it inline.\nAll operations respect the authenticated user's ACL permissions.\n",
                // Both endpoints are routed by Contena's own controllers (api.mcp.endpoint and
                // channel-api.mcp.endpoint), which apply authentication, rate limiting and the
                // capability allowlist. The bundle's controller and route loader stay switched off.
                'transports' => ['http' => false, 'stdio' => false],
                'registry' => $adminRegistry,
            ],
            'channel_api' => [
                'name' => 'Contena Channel API',
                'version' => '1.0.0',
                'description' => 'Contena Channel API MCP server for channel and member-context operations.',
                'instructions' => 'This MCP server exposes Channel API capabilities. All operations run in the current channel context and use Channel API authentication headers. The advertised tool list is not the full catalogue: if no advertised tool matches the requested action, call contena-tool-search first instead of assuming the action is unsupported, then use contena-toolsets-list and contena-toolset-enable to make a matched tool callable if your client cannot invoke it inline.',
                'transports' => ['http' => false, 'stdio' => false],
                'registry' => ['tools' => ['Contena\\Core\\System\\Channel\\Mcp\\']],
            ],
        ],
    ]);
};
