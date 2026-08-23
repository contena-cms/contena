<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Loader\StyleOptionSourceDirectory;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Contena\Core\Framework\DependencyInjection\DependencyInjectionException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Discovers style option YAML directories from core, bundles, and plugins, and
 * injects them into the YamlStyleOptionLoader.
 *
 * @internal
 */
final class ContentSystemStyleOptionCompilerPass implements CompilerPassInterface
{
    private const STANDARD_STYLE_OPTION_DIRECTORY = 'Resources/content-system/style-options';

    private const CORE_DEFINITIONS_DIRECTORY = __DIR__ . '/../../ContentSystem/Layout/Element/Style/Definitions';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(YamlStyleOptionLoader::class)) {
            return;
        }

        $directories = [];

        $this->addDirectory(self::CORE_DEFINITIONS_DIRECTORY, 'core', $directories);
        $this->loadFromBundleMetadata($container, $directories);

        $container->getDefinition(YamlStyleOptionLoader::class)->setArgument('$directories', $directories);
    }

    /**
     * @param list<Definition> $directories
     */
    private function loadFromBundleMetadata(ContainerBuilder $container, array &$directories): void
    {
        $bundleMetadata = $container->getParameter('kernel.bundles_metadata');
        if (!\is_array($bundleMetadata)) {
            throw DependencyInjectionException::bundlesMetadataIsNotAnArray();
        }

        $pluginBundleNames = $this->getActivePluginBundleNames($container);

        foreach ($bundleMetadata as $bundleName => $metadata) {
            if (!\is_array($metadata) || !isset($metadata['path']) || !\is_string($metadata['path'])) {
                continue;
            }

            // Plugins and bundles share the fixed convention directory; only the source label differs
            $source = (isset($pluginBundleNames[$bundleName]) ? 'plugin:' : 'bundle:') . $bundleName;

            $this->addDirectory($metadata['path'] . '/' . self::STANDARD_STYLE_OPTION_DIRECTORY, $source, $directories);
        }
    }

    /**
     * @return array<string, true>
     */
    private function getActivePluginBundleNames(ContainerBuilder $container): array
    {
        $activePlugins = $container->getParameter('kernel.active_plugins');
        if (!\is_array($activePlugins)) {
            throw DependencyInjectionException::parameterHasWrongType('kernel.active_plugins', 'array', get_debug_type($activePlugins));
        }

        $names = [];
        foreach ($activePlugins as $pluginMeta) {
            if (\is_array($pluginMeta) && isset($pluginMeta['name']) && \is_string($pluginMeta['name'])) {
                $names[$pluginMeta['name']] = true;
            }
        }

        return $names;
    }

    /**
     * @param list<Definition> $directories
     */
    private function addDirectory(string $directory, string $source, array &$directories): void
    {
        $directories[] = new Definition(StyleOptionSourceDirectory::class, [$source, $directory]);
    }
}
