<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan;

use Contena\Core\DevOps\StaticAnalyze\StaticAnalyzeKernel;
use Contena\Core\Framework\Adapter\Kernel\KernelFactory;
use Contena\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Symfony\Bundle\FrameworkBundle\Console\Application;

trigger_deprecation('contena/core', '', \sprintf('The "%s" file is deprecated and will be removed in v6.8.0.0 as the feature is no longer used in PHPStan', __FILE__));

$classLoader = require __DIR__ . '/phpstan-bootstrap.php';

$pluginLoader = new StaticKernelPluginLoader($classLoader);

KernelFactory::$kernelClass = StaticAnalyzeKernel::class;

/** @var StaticAnalyzeKernel $kernel */
$kernel = KernelFactory::create(
    environment: 'phpstan_dev',
    debug: true,
    classLoader: $classLoader,
    pluginLoader: $pluginLoader
);

$kernel->boot();

return new Application($kernel);
