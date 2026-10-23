<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\Action\ChangeUserStatusAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserCollection;

/**
 * @internal
 */
#[CoversClass(ChangeUserStatusAction::class)]
class ChangeUserStatusActionTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<UserCollection>
     */
    private Stub&EntityRepository $repository;

    private ChangeUserStatusAction $action;

    protected function setUp(): void
    {
        $this->repository = static::createStub(EntityRepository::class);
        $this->action = new ChangeUserStatusAction($this->repository);
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
        static::assertSame('action.change.user.status', ChangeUserStatusAction::getName());
    }

    public function testActionExecuted(): void
    {
        $userId = Uuid::randomHex();
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            UserAware::CUSTOMER_ID => $userId,
        ]);
        $flow->setConfig(['active' => true]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $userId, 'active' => true]]);

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
    private function createAction(?EntityRepository $repository = null): ChangeUserStatusAction
    {
        return new ChangeUserStatusAction($repository ?? $this->repository);
    }
}
