<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserProvider;
use Contena\Core\System\User\UserDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<UserProvider>
 */
#[CoversClass(UserProvider::class)]
class UserProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): UserProvider {
        return new UserProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return UserDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return [
            'salutation',
            'defaultBillingAddress.country',
            'defaultBillingAddress.countryState',
            'defaultBillingAddress.salutation',
            'defaultShippingAddress.country',
            'defaultShippingAddress.countryState',
            'defaultShippingAddress.salutation',
        ];
    }
}
