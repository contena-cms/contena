<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\Aware\MemberRecoveryAware;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\MemberRecoveryStorer;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberRecoveryProvider;
use Contena\Core\Framework\Context;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryEntity;
use Contena\Core\System\Member\Event\MemberAccountRecoverRequestEvent;
use Contena\Core\System\Member\Event\MemberRegisterEvent;

/**
 * @internal
 */
#[CoversClass(MemberRecoveryStorer::class)]
class MemberRecoveryStorerTest extends TestCase
{
    private MemberRecoveryStorer $storer;

    private MemberRecoveryProvider&Stub $memberRecoveryProvider;

    protected function setUp(): void
    {
        $this->memberRecoveryProvider = static::createStub(MemberRecoveryProvider::class);

        $this->storer = $this->createStorer($this->memberRecoveryProvider);
    }

    public function testStoreWithAware(): void
    {
        $event = static::createStub(MemberAccountRecoverRequestEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(MemberRecoveryAware::MEMBER_RECOVERY_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(MemberRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(MemberRecoveryAware::MEMBER_RECOVERY_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['memberRecoveryId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('memberRecovery', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertEmpty($storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $memberRecoveryProvider = $this->createMock(MemberRecoveryProvider::class);
        $storer = $this->createStorer($memberRecoveryProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['memberRecoveryId' => 'id']);
        $storer->restore($storable);
        $entity = new MemberRecoveryEntity();
        $entity->setId('id');

        $memberRecoveryProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('memberRecovery');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $memberRecoveryProvider = $this->createMock(MemberRecoveryProvider::class);
        $storer = $this->createStorer($memberRecoveryProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['memberRecoveryId' => 'id']);
        $storer->restore($storable);

        $memberRecoveryProvider->expects($this->once())->method('getData')->willReturn(null);
        $res = $storable->getData('memberRecovery');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['memberRecoveryId' => null], []);
        $this->storer->restore($storable);
        $memberGroup = $storable->getData('memberRecovery');

        static::assertNull($memberGroup);
    }

    private function createStorer(MemberRecoveryProvider $memberRecoveryProvider): MemberRecoveryStorer
    {
        return new MemberRecoveryStorer($memberRecoveryProvider);
    }
}
