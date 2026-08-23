<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\Event\BusinessEventRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BusinessEventRegisterCompilerPass implements CompilerPassInterface
{
    /**
     * @param list<class-string> $classes
     */
    public function __construct(private readonly array $classes)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition(BusinessEventRegistry::class)->addMethodCall('addClasses', [$this->classes]);
    }
}
