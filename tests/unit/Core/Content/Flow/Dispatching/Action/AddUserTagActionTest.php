<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\Action\AddUserTagAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(AddUserTagAction::class)]
class AddUserTagActionTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<UserCollection>
     */
    private Stub&EntityRepository $repository;

    private AddUserTagAction $action;

    protected function setUp(): void
    {
        $this->repository = static::createStub(EntityRepository::class);
        $this->action = new AddUserTagAction($this->repository);
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
        static::assertSame('action.add.user.tag', AddUserTagAction::getName());
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $expected
     */
    #[DataProvider('actionExecutedProvider')]
    public function testActionExecuted(array $config, array $expected): void
    {
        $userId = Uuid::randomHex();
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            UserAware::CUSTOMER_ID => $userId,
        ]);
        $flow->setConfig($config);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $userId, 'tags' => $expected]]);

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

    public static function actionExecutedProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Test with single tag' => [
            ['tagIds' => self::keys([$ids->get('tag-1')])],
            $ids->getIdArray(['tag-1']),
        ];

        yield 'Test with multiple tags' => [
            ['tagIds' => self::keys($ids->getList(['tag-1', 'tag-2']))],
            $ids->getIdArray(['tag-1', 'tag-2']),
        ];
    }

    /**
     * @param EntityRepository<UserCollection>|null $repository
     */
    private function createAction(?EntityRepository $repository = null): AddUserTagAction
    {
        return new AddUserTagAction($repository ?? $this->repository);
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string, true>
     */
    private static function keys(array $ids): array
    {
        return \array_combine($ids, \array_fill(0, \count($ids), true));
    }
}
