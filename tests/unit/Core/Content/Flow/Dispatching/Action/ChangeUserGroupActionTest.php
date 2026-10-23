<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Content\Flow\Dispatching\Action\ChangeUserGroupAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ChangeUserGroupAction::class)]
class ChangeUserGroupActionTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<UserCollection>
     */
    private Stub&EntityRepository $repository;

    private ChangeUserGroupAction $action;

    protected function setUp(): void
    {
        $this->repository = static::createStub(EntityRepository::class);
        $this->action = new ChangeUserGroupAction($this->repository);
    }

    public function testRequirements(): void
    {
        static::assertSame(
            [UserAware::class],
            $this->action->requirements()
        );
    }

    public function testName(): void
    {
        static::assertSame('action.change.user.group', ChangeUserGroupAction::getName());
    }

    public function testActionExecuted(): void
    {
        $groupId = Uuid::randomHex();
        $userId = Uuid::randomHex();

        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            UserAware::CUSTOMER_ID => $userId,
        ]);
        $flow->setConfig(['userGroupId' => $groupId]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $userId, 'groupId' => $groupId, 'requestedGroupId' => null]]);

        $this->createAction($repository)->handleFlow($flow);
    }

    public function testActionWithNotAware(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext());

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('update');

        $this->createAction($repository)->handleFlow($flow);
    }

    public function testActionWithEmptyConfig(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            UserAware::CUSTOMER_ID => Uuid::randomHex(),
        ]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('update');

        $this->createAction($repository)->handleFlow($flow);
    }

    /**
     * @param EntityRepository<UserCollection>|null $repository
     */
    private function createAction(?EntityRepository $repository = null): ChangeUserGroupAction
    {
        return new ChangeUserGroupAction($repository ?? $this->repository);
    }
}
