<?php declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Path;

/** @codeCoverageIgnore */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    if (!$builder->hasExtension('mcp')) {
        return;
    }

    $projectDir = (string) $builder->getParameter('kernel.project_dir');
    $bundles = $builder->getParameter('kernel.bundles_metadata');
    $scanDirs = [Path::makeRelative($bundles['Framework']['path'] . '/Mcp', $projectDir)];
    if (isset($bundles['Frontend'])) {
        $scanDirs[] = Path::makeRelative($bundles['Frontend']['path'] . '/Mcp', $projectDir);
    }

    $container->extension('mcp', [
        'app' => 'Contena',
        'version' => '1.0.0',
        'description' => 'Contena generic administration MCP server.',
        'instructions' => "This MCP server exposes generic Contena administration capabilities.\nUse entity tools for DAL data, system-config tools for configuration, and always respect the authenticated user's ACL.\nIf no advertised tool matches the requested action, call contena-tool-search, then use contena-toolsets-list and contena-toolset-enable to make the matching tool callable.\n",
        'client_transports' => ['http' => true],
        'http' => ['path' => '/api/_mcp'],
        'discovery' => ['scan_dirs' => $scanDirs],
    ]);
};
