<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Increment;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Increment\AbstractIncrementer;
use Contena\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Contena\Core\Framework\Increment\IncrementGatewayRegistry;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class IncrementerGatewayRegistryTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetUserActivityPool(): void
    {
        $registry = static::getContainer()->get('contena.increment.gateway.registry');

        static::assertInstanceOf(AbstractIncrementer::class, $registry->get(IncrementGatewayRegistry::USER_ACTIVITY_POOL));
    }

    /**
     * @deprecated tag:v6.8.0 - Test will be removed
     */
    public function testGetMessageQueuePool(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $registry = static::getContainer()->get('contena.increment.gateway.registry');

        static::assertInstanceOf(AbstractIncrementer::class, $registry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL));
    }

    public function testGetWithInvalidPool(): void
    {
        $this->expectExceptionObject(new IncrementGatewayNotFoundException('custom_pool'));

        $registry = static::getContainer()->get('contena.increment.gateway.registry');
        $registry->get('custom_pool');
    }
}
