<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\Aware\UserRecoveryAware;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\UserRecoveryStorer;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserRecoveryProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Event\UserAccountRecoverRequestEvent;
use Contena\Core\System\User\Event\UserRegisterEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(UserRecoveryStorer::class)]
class UserRecoveryStorerTest extends TestCase
{
    private UserRecoveryStorer $storer;

    private UserRecoveryProvider&Stub $userRecoveryProvider;

    protected function setUp(): void
    {
        $this->userRecoveryProvider = static::createStub(UserRecoveryProvider::class);

        $this->storer = $this->createStorer($this->userRecoveryProvider);
    }

    public function testStoreWithAware(): void
    {
        $event = static::createStub(UserAccountRecoverRequestEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(UserRecoveryAware::CUSTOMER_RECOVERY_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(UserRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(UserRecoveryAware::CUSTOMER_RECOVERY_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userRecoveryId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('userRecovery', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertCount(0, $storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $userRecoveryProvider = $this->createMock(UserRecoveryProvider::class);
        $storer = $this->createStorer($userRecoveryProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userRecoveryId' => 'id']);
        $storer->restore($storable);
        $entity = new UserRecoveryEntity();
        $entity->setId('id');

        $userRecoveryProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('userRecovery');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $userRecoveryProvider = $this->createMock(UserRecoveryProvider::class);
        $storer = $this->createStorer($userRecoveryProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userRecoveryId' => 'id']);
        $storer->restore($storable);

        $userRecoveryProvider->expects($this->once())->method('getData')->willReturn(null);
        $res = $storable->getData('userRecovery');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userRecoveryId' => null], []);
        $this->storer->restore($storable);
        $userGroup = $storable->getData('userRecovery');

        static::assertNull($userGroup);
    }

    private function createStorer(UserRecoveryProvider $userRecoveryProvider): UserRecoveryStorer
    {
        return new UserRecoveryStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $userRecoveryProvider,
        );
    }
}
