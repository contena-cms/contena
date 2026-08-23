<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\MemberStorer;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\MemberAware;
use Contena\Core\System\Member\Event\MemberRegisterEvent;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;

/**
 * @internal
 */
#[CoversClass(MemberStorer::class)]
class MemberStorerTest extends TestCase
{
    private MemberStorer $storer;

    private Stub&MemberProvider $memberProvider;

    protected function setUp(): void
    {
        $this->memberProvider = static::createStub(MemberProvider::class);

        $this->storer = $this->createStorer($this->memberProvider);
    }

    public function testStoreWithAware(): void
    {
        $event = static::createStub(MemberRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(MemberAware::MEMBER_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(UserRecoveryRequestEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(MemberAware::MEMBER_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['memberId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('member', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertEmpty($storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $memberProvider = $this->createMock(MemberProvider::class);
        $storer = $this->createStorer($memberProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['memberId' => 'id'], []);
        $storer->restore($storable);
        $entity = new MemberEntity();
        $entity->setId('id');

        $memberProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('member');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $memberProvider = $this->createMock(MemberProvider::class);
        $storer = $this->createStorer($memberProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['memberId' => 'id'], []);
        $storer->restore($storable);

        $memberProvider->expects($this->once())->method('getData')->willReturn(null);
        $res = $storable->getData('member');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['memberId' => null], []);
        $this->storer->restore($storable);
        $member = $storable->getData('member');

        static::assertNull($member);
    }

    private function createStorer(MemberProvider $memberProvider): MemberStorer
    {
        return new MemberStorer($memberProvider);
    }
}
