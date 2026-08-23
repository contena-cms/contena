<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy;

use Contena\Core\Framework\Bundle;
use Symfony\Component\HttpKernel\KernelInterface;

class BundleHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public function buildNamespaceHierarchy(array $namespaceHierarchy): array
    {
        /*
         * Priority system: Lower integer = higher precedence
         * Example: -2 overrides 0, which overrides 1
         * Used only for sorting, then discarded
         */
        $bundles = [];

        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $bundlePath = $bundle->getPath();

            $directory = $bundlePath . '/Resources/views';

            if (!\is_dir($directory)) {
                continue;
            }

            $bundles[$bundle->getName()] = $bundle->getTemplatePriority();
        }

        // Contena registers bundles in reverse order
        $bundles = array_reverse($bundles);

        asort($bundles);

        // Chain with existing hierarchy
        return array_merge(
            $bundles,
            $namespaceHierarchy
        );
    }
}
