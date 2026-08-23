<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\DependencyInjection\DependencyInjectionException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DefaultTransportCompilerPass implements CompilerPassInterface
{
    use CompilerPassConfigTrait;

    public function process(ContainerBuilder $container): void
    {
        // the default transport is defined by the parameter `messenger.default_transport_name`
        $defaultName = $container->getParameter('messenger.default_transport_name');
        if (!\is_string($defaultName)) {
            throw DependencyInjectionException::parameterHasWrongType('messenger.default_transport_name', 'string', get_debug_type($defaultName));
        }
        $id = 'messenger.transport.' . $defaultName;
        $container->addAliases(['messenger.default_transport' => $id]);
    }
}
