<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\UserStorer;
use Contena\Core\Content\Flow\Exception\UserDeletedException;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryRequestEvent;
use Contena\Core\System\User\Event\UserRegisterEvent;
use Contena\Core\System\User\UserEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(UserStorer::class)]
class UserStorerTest extends TestCase
{
    private UserStorer $storer;

    private Stub&UserProvider $userProvider;

    protected function setUp(): void
    {
        $this->userProvider = static::createStub(UserProvider::class);

        $this->storer = $this->createStorer($this->userProvider);
    }

    public function testStoreWithAware(): void
    {
        $event = static::createStub(UserRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(UserAware::CUSTOMER_ID, $stored);
    }

    public function testStoreWillCatchUserDeleteException(): void
    {
        $event = static::createStub(OrderStateMachineStateChangeEvent::class);
        $event->method('getUserId')->willThrowException(new UserDeletedException('id'));

        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(UserAware::CUSTOMER_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(AdminUserRecoveryRequestEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(UserAware::CUSTOMER_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('user', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertCount(0, $storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $userProvider = $this->createMock(UserProvider::class);
        $storer = $this->createStorer($userProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userId' => 'id'], []);
        $storer->restore($storable);
        $entity = new UserEntity();
        $entity->setId('id');

        $userProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('user');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $userProvider = $this->createMock(UserProvider::class);
        $storer = $this->createStorer($userProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userId' => 'id'], []);
        $storer->restore($storable);

        $userProvider->expects($this->once())->method('getData')->willReturn(null);
        $res = $storable->getData('user');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userId' => null], []);
        $this->storer->restore($storable);
        $userGroup = $storable->getData('user');

        static::assertNull($userGroup);
    }

    private function createStorer(UserProvider $userProvider): UserStorer
    {
        return new UserStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $userProvider,
        );
    }
}
