<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\Event\UserGroupRegistrationDeclined;
use Contena\Core\System\User\Event\UserRegisterEvent;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\UserGroupStorer;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserGroupProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\UserGroupAware;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(UserGroupStorer::class)]
class UserGroupStorerTest extends TestCase
{
    private UserGroupStorer $storer;

    private Stub&UserGroupProvider $userGroupProvider;

    protected function setUp(): void
    {
        $this->userGroupProvider = static::createStub(UserGroupProvider::class);

        $this->storer = $this->buildStorer($this->userGroupProvider);
    }

    public function testStoreWithAware(): void
    {
        $event = static::createStub(UserGroupRegistrationDeclined::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(UserGroupAware::CUSTOMER_GROUP_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(UserRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(UserGroupAware::CUSTOMER_GROUP_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userGroupId' => 'test_id']);

        $this->storer->restore($storable);
        static::assertArrayHasKey('userGroup', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);
        static::assertCount(0, $storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userGroupId' => 'id'], []);

        $userGroupProvider = $this->createMock(UserGroupProvider::class);
        $storer = $this->buildStorer($userGroupProvider);

        $storer->restore($storable);
        $entity = new UserGroupEntity();
        $entity->setId('id');

        $userGroupProvider->expects($this->once())->method('getData')->willReturn($entity);
        $userGroup = $storable->getData('userGroup');

        static::assertSame($userGroup, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userGroupId' => 'id'], []);

        $userGroupProvider = $this->createMock(UserGroupProvider::class);
        $storer = $this->buildStorer($userGroupProvider);
        $storer->restore($storable);

        $userGroupProvider->expects($this->once())->method('getData')->willReturn(null);
        $userGroup = $storable->getData('userGroup');

        static::assertNull($userGroup);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userGroupId' => null], []);
        $this->storer->restore($storable);
        $userGroup = $storable->getData('userGroup');

        static::assertNull($userGroup);
    }

    private function buildStorer(UserGroupProvider $userGroupProvider): UserGroupStorer
    {
        return new UserGroupStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $userGroupProvider
        );
    }
}
