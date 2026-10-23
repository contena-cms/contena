<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Contena\Core\Content\Shared\MailFlow\DataProvider\AdminUserRecoveryProvider;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<AdminUserRecoveryProvider>
 */
#[CoversClass(AdminUserRecoveryProvider::class)]
class AdminUserRecoveryProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): AdminUserRecoveryProvider {
        return new AdminUserRecoveryProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return AdminUserRecoveryDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return ['admin_user'];
    }
}
