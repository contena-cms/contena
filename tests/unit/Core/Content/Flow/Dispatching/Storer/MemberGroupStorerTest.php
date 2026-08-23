<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\MemberGroupStorer;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberGroupProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\MemberGroupAware;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\Event\MemberGroupRegistrationDeclined;
use Contena\Core\System\Member\Event\MemberRegisterEvent;

/**
 * @internal
 */
#[CoversClass(MemberGroupStorer::class)]
class MemberGroupStorerTest extends TestCase
{
    private MemberGroupStorer $storer;

    private Stub&MemberGroupProvider $memberGroupProvider;

    protected function setUp(): void
    {
        $this->memberGroupProvider = static::createStub(MemberGroupProvider::class);

        $this->storer = $this->buildStorer($this->memberGroupProvider);
    }

    public function testStoreWithAware(): void
    {
        $event = static::createStub(MemberGroupRegistrationDeclined::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(MemberGroupAware::MEMBER_GROUP_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(MemberRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(MemberGroupAware::MEMBER_GROUP_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['memberGroupId' => 'test_id']);

        $this->storer->restore($storable);
        static::assertArrayHasKey('memberGroup', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);
        static::assertEmpty($storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['memberGroupId' => 'id'], []);

        $memberGroupProvider = $this->createMock(MemberGroupProvider::class);
        $storer = $this->buildStorer($memberGroupProvider);

        $storer->restore($storable);
        $entity = new MemberGroupEntity();
        $entity->setId('id');

        $memberGroupProvider->expects($this->once())->method('getData')->willReturn($entity);
        $memberGroup = $storable->getData('memberGroup');

        static::assertSame($memberGroup, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['memberGroupId' => 'id'], []);

        $memberGroupProvider = $this->createMock(MemberGroupProvider::class);
        $storer = $this->buildStorer($memberGroupProvider);
        $storer->restore($storable);

        $memberGroupProvider->expects($this->once())->method('getData')->willReturn(null);
        $memberGroup = $storable->getData('memberGroup');

        static::assertNull($memberGroup);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['memberGroupId' => null], []);
        $this->storer->restore($storable);
        $memberGroup = $storable->getData('memberGroup');

        static::assertNull($memberGroup);
    }

    private function buildStorer(MemberGroupProvider $memberGroupProvider): MemberGroupStorer
    {
        return new MemberGroupStorer($memberGroupProvider);
    }
}
