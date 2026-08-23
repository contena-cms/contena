<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Plugin;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Plugin\KernelPluginCollection;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Framework\Plugin\PluginCollection;
use Contena\Core\Framework\Plugin\PluginService;
use Contena\Core\Framework\Plugin\Util\PluginFinder;
use Contena\Core\Framework\Plugin\Util\VersionSanitizer;
use Contena\Core\System\Language\LanguageCollection;
use CtTestPlugin\CtTestPlugin;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait PluginTestsHelper
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepo
     * @param EntityRepository<LanguageCollection> $languageRepo
     */
    protected function createPluginService(
        string $pluginDir,
        string $projectDir,
        EntityRepository $pluginRepo,
        EntityRepository $languageRepo,
        PluginFinder $pluginFinder
    ): PluginService {
        return new PluginService(
            $pluginDir,
            $projectDir,
            $pluginRepo,
            $languageRepo,
            $pluginFinder,
            new VersionSanitizer()
        );
    }

    /**
     * @param EntityRepository<PluginCollection> $pluginRepo
     */
    protected function createPlugin(
        EntityRepository $pluginRepo,
        Context $context,
        string $version = CtTestPlugin::PLUGIN_VERSION,
        ?string $installedAt = null
    ): void {
        $pluginRepo->create(
            [
                [
                    'baseClass' => CtTestPlugin::class,
                    'name' => 'CtTestPlugin',
                    'version' => $version,
                    'label' => CtTestPlugin::PLUGIN_LABEL,
                    'installedAt' => $installedAt,
                    'active' => false,
                    'autoload' => [],
                ],
            ],
            $context
        );
    }

    abstract protected static function getContainer(): ContainerInterface;

    private function addTestPluginToKernel(string $testPluginBaseDir, string $pluginName, bool $active = false): void
    {
        require_once $testPluginBaseDir . '/src/' . $pluginName . '.php';

        $class = '\\' . $pluginName . '\\' . $pluginName;
        $plugin = new $class($active, $testPluginBaseDir);
        static::assertInstanceOf(Plugin::class, $plugin);
        static::getContainer()->get(KernelPluginCollection::class)->add($plugin);

        static::getContainer()->get(KernelPluginLoader::class)->getPluginInstances()->add($plugin);
    }
}
