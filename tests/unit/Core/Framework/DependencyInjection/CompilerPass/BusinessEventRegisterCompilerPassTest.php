<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DependencyInjection\CompilerPass\BusinessEventRegisterCompilerPass;
use Contena\Core\Framework\Event\BusinessEventRegistry;
use Contena\Core\Framework\Framework;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BusinessEventRegisterCompilerPass::class)]
class BusinessEventRegisterCompilerPassTest extends TestCase
{
    public function testEventsGetAdded(): void
    {
        $container = new ContainerBuilder();
        $container->register(BusinessEventRegistry::class)
            ->setPublic(true);

        $container->addCompilerPass(new BusinessEventRegisterCompilerPass([Framework::class]));

        $container->compile();
        static::assertContains(Framework::class, $container->get(BusinessEventRegistry::class)->getClasses());
    }
}
