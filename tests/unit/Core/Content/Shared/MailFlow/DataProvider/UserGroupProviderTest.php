<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserGroupProvider;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<UserGroupProvider>
 */
#[CoversClass(UserGroupProvider::class)]
class UserGroupProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): UserGroupProvider {
        return new UserGroupProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return UserGroupDefinition::ENTITY_NAME;
    }
}
