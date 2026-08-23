<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\ScheduledTask\DynamicallyScheduledTaskHandler;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskExecutor;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(ScheduledTaskExecutor::class)]
class ScheduledTaskExecutorTest extends TestCase
{
    public function testRunsWithoutScheduleWhenTaskHasNoId(): void
    {
        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([]);

        $handler = $this->createHandler($repository);
        $executor = new ScheduledTaskExecutor($repository, static::createStub(LoggerInterface::class), new MockClock());

        $executor->execute($handler, new TestExecutorTask());

        static::assertTrue($handler->wasCalled);
        static::assertSame([], $repository->updates);
    }

    public function testReturnsEarlyWhenTaskNotFound(): void
    {
        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([new ScheduledTaskCollection()]);

        $handler = $this->createHandler($repository);
        $executor = new ScheduledTaskExecutor($repository, static::createStub(LoggerInterface::class), new MockClock());

        $executor->execute($handler, $this->createTask());

        static::assertFalse($handler->wasCalled);
        static::assertSame([], $repository->updates);
    }

    public function testReturnsEarlyWhenExecutionIsNotAllowed(): void
    {
        $task = $this->createTask();
        $entity = $this->createEntity($task->getTaskId(), ScheduledTaskDefinition::STATUS_SCHEDULED);

        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([new ScheduledTaskCollection([$entity])]);

        $handler = $this->createHandler($repository);
        $executor = new ScheduledTaskExecutor($repository, static::createStub(LoggerInterface::class), new MockClock());

        $executor->execute($handler, $task);

        static::assertFalse($handler->wasCalled);
        static::assertSame([], $repository->updates);
    }

    public function testHappyPathMarksRunningAndReschedules(): void
    {
        $task = $this->createTask();
        $now = new \DateTimeImmutable('2026-06-08 12:00:00');
        $entity = $this->createEntity(
            $task->getTaskId(),
            ScheduledTaskDefinition::STATUS_QUEUED,
            new \DateTimeImmutable('2026-06-08 11:59:00'),
            300,
        );

        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([new ScheduledTaskCollection([$entity])]);

        $handler = $this->createHandler($repository);
        $executor = new ScheduledTaskExecutor($repository, static::createStub(LoggerInterface::class), new MockClock($now));

        $executor->execute($handler, $task);

        static::assertTrue($handler->wasCalled);

        $payloads = $repository->getPayloads(StaticEntityRepository::UPDATE);
        static::assertCount(2, $payloads);

        // first the task is marked running
        static::assertSame(ScheduledTaskDefinition::STATUS_RUNNING, $payloads[0]['status']);
        static::assertSame($task->getTaskId(), $payloads[0]['id']);

        // then it gets rescheduled
        static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $payloads[1]['status']);
        static::assertEquals($now, $payloads[1]['lastExecutionTime']);
        static::assertEquals(new \DateTimeImmutable('2026-06-08 12:04:00'), $payloads[1]['nextExecutionTime']);
    }

    public function testRescheduleUsesNowWhenNextExecutionIsInThePast(): void
    {
        $task = $this->createTask();
        $now = new \DateTimeImmutable('2026-06-08 12:00:00');
        $entity = $this->createEntity(
            $task->getTaskId(),
            ScheduledTaskDefinition::STATUS_QUEUED,
            new \DateTimeImmutable('2026-06-07 12:00:00'),
            60,
        );

        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([new ScheduledTaskCollection([$entity])]);

        $handler = $this->createHandler($repository);
        $executor = new ScheduledTaskExecutor($repository, static::createStub(LoggerInterface::class), new MockClock($now));

        $executor->execute($handler, $task);

        $payloads = $repository->getPayloads(StaticEntityRepository::UPDATE);
        static::assertEquals($now, $payloads[1]['nextExecutionTime']);
    }

    public function testFailureWithoutRescheduleMarksFailedAndRethrows(): void
    {
        $task = $this->createTask();
        $entity = $this->createEntity($task->getTaskId(), ScheduledTaskDefinition::STATUS_QUEUED);

        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([new ScheduledTaskCollection([$entity])]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $handler = $this->createHandler($repository, throw: new \RuntimeException('boom'));
        $executor = new ScheduledTaskExecutor($repository, $logger, new MockClock());

        try {
            $executor->execute($handler, $task);
            static::fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            static::assertSame('boom', $e->getMessage());
        }

        $payloads = $repository->getPayloads(StaticEntityRepository::UPDATE);
        static::assertCount(2, $payloads);
        static::assertSame(ScheduledTaskDefinition::STATUS_RUNNING, $payloads[0]['status']);
        static::assertSame(ScheduledTaskDefinition::STATUS_FAILED, $payloads[1]['status']);
    }

    public function testFailureWithRescheduleOnFailureLogsAndReschedules(): void
    {
        $task = new TestRescheduleExecutorTask();
        $task->setTaskId(Uuid::randomHex());
        $entity = $this->createEntity($task->getTaskId(), ScheduledTaskDefinition::STATUS_QUEUED);

        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([new ScheduledTaskCollection([$entity])]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $handler = $this->createHandler($repository, throw: new \RuntimeException('boom'));
        $executor = new ScheduledTaskExecutor($repository, $logger, new MockClock());

        $executor->execute($handler, $task);

        $payloads = $repository->getPayloads(StaticEntityRepository::UPDATE);
        static::assertCount(2, $payloads);
        static::assertSame(ScheduledTaskDefinition::STATUS_RUNNING, $payloads[0]['status']);
        static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $payloads[1]['status']);
    }

    public function testDynamicHandlerNextExecutionTimeIsPersisted(): void
    {
        $task = $this->createTask();
        $now = new \DateTimeImmutable('2026-06-08 12:00:00');
        $next = new \DateTimeImmutable('2026-06-09 08:00:00');
        $entity = $this->createEntity($task->getTaskId(), ScheduledTaskDefinition::STATUS_QUEUED);

        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([new ScheduledTaskCollection([$entity])]);

        $handler = new TestDynamicExecutorHandler($repository, static::createStub(LoggerInterface::class), $next);
        $executor = new ScheduledTaskExecutor($repository, static::createStub(LoggerInterface::class), new MockClock($now));

        $executor->execute($handler, $task);

        $payloads = $repository->getPayloads(StaticEntityRepository::UPDATE);
        static::assertCount(2, $payloads);
        static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $payloads[1]['status']);
        static::assertEquals($next, $payloads[1]['nextExecutionTime']);
    }

    public function testDynamicHandlerFallsBackToDefaultScheduleWhenNull(): void
    {
        $task = $this->createTask();
        $now = new \DateTimeImmutable('2026-06-08 12:00:00');
        $entity = $this->createEntity(
            $task->getTaskId(),
            ScheduledTaskDefinition::STATUS_QUEUED,
            new \DateTimeImmutable('2026-06-08 11:59:00'),
            300,
        );

        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([new ScheduledTaskCollection([$entity])]);

        $handler = new TestDynamicExecutorHandler($repository, static::createStub(LoggerInterface::class), null);
        $executor = new ScheduledTaskExecutor($repository, static::createStub(LoggerInterface::class), new MockClock($now));

        $executor->execute($handler, $task);

        $payloads = $repository->getPayloads(StaticEntityRepository::UPDATE);
        static::assertEquals(new \DateTimeImmutable('2026-06-08 12:04:00'), $payloads[1]['nextExecutionTime']);
    }

    public function testDynamicHandlerNextExecutionTimeIsClampedToNow(): void
    {
        $task = $this->createTask();
        $now = new \DateTimeImmutable('2026-06-08 12:00:00');
        $entity = $this->createEntity($task->getTaskId(), ScheduledTaskDefinition::STATUS_QUEUED);

        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([new ScheduledTaskCollection([$entity])]);

        // the handler returns a time in the past, which must be clamped to now
        $handler = new TestDynamicExecutorHandler($repository, static::createStub(LoggerInterface::class), new \DateTimeImmutable('2026-06-07 00:00:00'));
        $executor = new ScheduledTaskExecutor($repository, static::createStub(LoggerInterface::class), new MockClock($now));

        $executor->execute($handler, $task);

        $payloads = $repository->getPayloads(StaticEntityRepository::UPDATE);
        static::assertEquals($now, $payloads[1]['nextExecutionTime']);
    }

    /**
     * @param StaticEntityRepository<ScheduledTaskCollection> $repository
     */
    private function createHandler(EntityRepository $repository, ?\Throwable $throw = null): TestExecutorHandler
    {
        return new TestExecutorHandler($repository, static::createStub(LoggerInterface::class), $throw);
    }

    private function createTask(): TestExecutorTask
    {
        $task = new TestExecutorTask();
        $task->setTaskId(Uuid::randomHex());

        return $task;
    }

    private function createEntity(
        ?string $id,
        string $status,
        ?\DateTimeInterface $nextExecutionTime = null,
        int $runInterval = 300,
    ): ScheduledTaskEntity {
        $entity = new ScheduledTaskEntity();
        $entity->setId($id ?? Uuid::randomHex());
        $entity->setStatus($status);
        $entity->setRunInterval($runInterval);
        $entity->setNextExecutionTime($nextExecutionTime ?? new \DateTimeImmutable());

        return $entity;
    }
}

/**
 * @internal
 */
class TestExecutorTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'test.executor';
    }

    public static function getDefaultInterval(): int
    {
        return 300;
    }
}

/**
 * @internal
 */
class TestRescheduleExecutorTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'test.executor.reschedule';
    }

    public static function getDefaultInterval(): int
    {
        return 300;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}

/**
 * @internal
 */
class TestExecutorHandler extends ScheduledTaskHandler
{
    public bool $wasCalled = false;

    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly ?\Throwable $throw = null,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->wasCalled = true;

        if ($this->throw !== null) {
            throw $this->throw;
        }
    }
}

/**
 * @internal
 */
class TestDynamicExecutorHandler extends ScheduledTaskHandler implements DynamicallyScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly ?\DateTimeInterface $nextExecutionTime,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
    }

    public function getNextExecutionTime(ScheduledTask $task, ScheduledTaskEntity $taskEntity): ?\DateTimeInterface
    {
        return $this->nextExecutionTime;
    }
}
