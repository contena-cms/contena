<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Contena\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Contena\Core\Content\Shared\MailFlow\DataProvider\CustomerRecoveryProvider;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<CustomerRecoveryProvider>
 */
#[Package('after-sales')]
#[CoversClass(CustomerRecoveryProvider::class)]
class CustomerRecoveryProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): CustomerRecoveryProvider {
        return new CustomerRecoveryProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return CustomerRecoveryDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return ['customer.salutation'];
    }
}
