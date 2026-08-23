<?php declare(strict_types=1);

use Composer\InstalledVersions;

$bundles = [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true, 'test' => true],
    Contena\Core\Framework\Framework::class => ['all' => true],
    Contena\Core\System\System::class => ['all' => true],
    Contena\Core\Content\Content::class => ['all' => true],
    Contena\Core\DevOps\DevOps::class => ['all' => true],
    Contena\Core\Maintenance\Maintenance::class => ['all' => true],
    Contena\Administration\Administration::class => ['all' => true],
    Contena\Frontend\Frontend::class => ['all' => true],
    Contena\Elasticsearch\Elasticsearch::class => ['all' => true],
];

if (InstalledVersions::isInstalled('symfony/web-profiler-bundle')) {
    $bundles[Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class] = ['dev' => true, 'test' => true, 'phpstan_dev' => true];
}

if (InstalledVersions::isInstalled('symfony/mcp-bundle')) {
    $bundles[Symfony\AI\McpBundle\McpBundle::class] = ['all' => true];
}

return $bundles;
