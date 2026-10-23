<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\Framework\Adapter\Database;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Database\ReplicaConnectionResetter;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetterInterface;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class ReplicaConnectionResetterTest extends TestCase
{
    use KernelTestBehaviour;

    // Remove this test with the Framework::boot() workaround once a Symfony upgrade proves the resetter initializes this service.
    public function testServicesResetterInitializesReplicaConnectionResetter(): void
    {
        $container = static::getContainer();

        $servicesResetter = $container->get('services_resetter');
        static::assertInstanceOf(ServicesResetterInterface::class, $servicesResetter);
        $servicesResetter->reset();

        // Once Symfony initializes all kernel.reset services itself, this remains true without
        // Framework::boot() fetching the service and the boot-time workaround can be removed.
        static::assertTrue($container->initialized(ReplicaConnectionResetter::class));
    }
}
