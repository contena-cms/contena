<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Kernel;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Adapter\Database\MySQLFactory;
use Contena\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Framework\Telemetry\Doctrine\QueryCountMiddleware;
use Contena\Core\Kernel;
use Contena\Core\Profiling\Doctrine\ProfilingMiddleware;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Contena\Core\Framework\Adapter\Kernel\KernelFactory
 *      Contena\Core\Kernel
 *          Contena\Core\Framework\Adapter\Kernel\HttpCacheKernel (http caching)
 *              Contena\Core\Framework\Adapter\Kernel\HttpKernel (runs request transformer)
 *                  Contena\Frontend\Controller\Any
 *
 * @final
 */
class KernelFactory
{
    /**
     * @var class-string<Kernel>
     */
    public static string $kernelClass = Kernel::class;

    public static function create(
        string $environment,
        bool $debug,
        ClassLoader $classLoader,
        ?KernelPluginLoader $pluginLoader = null,
        ?Connection $connection = null
    ): HttpKernelInterface {
        if (InstalledVersions::isInstalled('contena/platform')) {
            $contenaVersion = Kernel::CONTENA_FALLBACK_VERSION
                . '@' . InstalledVersions::getReference('contena/platform');
        } else {
            $contenaVersion = InstalledVersions::getVersion('contena/core')
                . '@' . InstalledVersions::getReference('contena/core');
        }

        $middlewares = [];
        if ((\PHP_SAPI !== 'cli' || \in_array('--profile', $_SERVER['argv'] ?? [], true))
            && $environment !== 'prod' && InstalledVersions::isInstalled('symfony/doctrine-bridge')) {
            $middlewares = [new ProfilingMiddleware()];
        }

        // Counts SQL statements per request for the http.server.request.queries.count metric.
        // The middleware must wrap the driver at connection creation, before the container exists.
        $middlewares[] = new QueryCountMiddleware();

        $connection ??= MySQLFactory::create($middlewares);

        $pluginLoader ??= new DbalKernelPluginLoader($classLoader, null, $connection);

        $cacheId = (string) EnvironmentHelper::getVariable('CONTENA_CACHE_ID', '');

        $kernel = new static::$kernelClass(
            $environment,
            $debug,
            $pluginLoader,
            $cacheId,
            $contenaVersion,
            $connection,
            self::getProjectDir()
        );

        return $kernel;
    }

    private static function getProjectDir(): string
    {
        if ($dir = $_ENV['PROJECT_ROOT'] ?? $_SERVER['PROJECT_ROOT'] ?? false) {
            return $dir;
        }

        $r = new \ReflectionClass(self::class);

        /** @var non-empty-string $dir */
        $dir = $r->getFileName();

        $dir = $rootDir = \dirname($dir);
        while (!\is_dir($dir . '/vendor')) {
            if ($dir === \dirname($dir)) {
                return $rootDir;
            }
            $dir = \dirname($dir);
        }

        return $dir;
    }
}
