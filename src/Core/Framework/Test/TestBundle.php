<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test;

use Contena\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @internal
 */
#[Package('framework')]
class TestBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemoveDeprecatedServicesPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);

        parent::build($container);
    }
}
