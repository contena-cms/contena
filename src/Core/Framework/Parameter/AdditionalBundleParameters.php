<?php declare(strict_types=1);

namespace Contena\Core\Framework\Parameter;

use Composer\Autoload\ClassLoader;
use Contena\Core\Framework\Plugin\KernelPluginCollection;

final class AdditionalBundleParameters
{
    public function __construct(
        private ClassLoader $classLoader,
        private KernelPluginCollection $pluginInstances,
        private array $kernelParameters
    ) {
    }

    public function getClassLoader(): ClassLoader
    {
        return $this->classLoader;
    }

    public function setClassLoader(ClassLoader $classLoader): self
    {
        $this->classLoader = $classLoader;

        return $this;
    }

    public function getPluginInstances(): KernelPluginCollection
    {
        return $this->pluginInstances;
    }

    public function setPluginInstances(KernelPluginCollection $pluginInstances): self
    {
        $this->pluginInstances = $pluginInstances;

        return $this;
    }

    public function getKernelParameters(): array
    {
        return $this->kernelParameters;
    }

    public function setKernelParameters(array $kernelParameters): self
    {
        $this->kernelParameters = $kernelParameters;

        return $this;
    }
}
