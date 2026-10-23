<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\Definition\Processor;

trait CompilerPassConfigTrait
{
    /**
     * @return array<mixed>
     */
    public function getConfig(ContainerBuilder $container, string $bundle): array
    {
        $config = $container->getExtensionConfig($bundle);

        $resolvingBag = $container->getParameterBag();
        $config = $resolvingBag->resolveValue($config);

        return (new Processor())
            ->processConfiguration(
                new Configuration($container->getParameter('kernel.debug')),
                $config,
            );
    }
}
