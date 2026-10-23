<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\AdminUserStorer;
use Contena\Core\Content\Shared\MailFlow\DataProvider\AdminUserRecoveryProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\AdminUserAware;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryEntity;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryRequestEvent;
use Contena\Core\System\User\Event\UserRegisterEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(AdminUserStorer::class)]
class AdminUserStorerTest extends TestCase
{
    private AdminUserStorer $storer;

    private Stub&AdminUserRecoveryProvider $adminUserRecoveryProvider;

    protected function setUp(): void
    {
        $this->admin_userRecoveryProvider = static::createStub(AdminUserRecoveryProvider::class);

        $this->storer = $this->createStorer($this->admin_userRecoveryProvider);
    }

    public function testStoreWithAware(): void
    {
        $event = static::createStub(AdminUserRecoveryRequestEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(AdminUserAware::USER_RECOVERY_ID, $stored);
    }

    public function testStore(): void
    {
        $event = static::createStub(UserRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(AdminUserAware::USER_RECOVERY_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $this->admin_userRecoveryProvider->method('getData')->willReturn(new AdminUserRecoveryEntity());

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['admin_userRecoveryId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('admin_userRecovery', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertCount(0, $storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $adminUserRecoveryProvider = $this->createMock(AdminUserRecoveryProvider::class);
        $storer = $this->createStorer($adminUserRecoveryProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['admin_userRecoveryId' => 'id'], []);
        $storer->restore($storable);
        $entity = new AdminUserRecoveryEntity();
        $entity->setId('id');

        $adminUserRecoveryProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('admin_userRecovery');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $adminUserRecoveryProvider = $this->createMock(AdminUserRecoveryProvider::class);
        $storer = $this->createStorer($adminUserRecoveryProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['admin_userRecoveryId' => 'id'], []);
        $storer->restore($storable);

        $adminUserRecoveryProvider->expects($this->once())->method('getData')->willReturn(null);
        $res = $storable->getData('admin_userRecovery');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['admin_userRecoveryId' => null], []);
        $this->storer->restore($storable);
        $userGroup = $storable->getData('admin_userRecovery');

        static::assertNull($userGroup);
    }

    private function createStorer(AdminUserRecoveryProvider $adminUserRecoveryProvider): AdminUserStorer
    {
        return new AdminUserStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $adminUserRecoveryProvider,
        );
    }
}
