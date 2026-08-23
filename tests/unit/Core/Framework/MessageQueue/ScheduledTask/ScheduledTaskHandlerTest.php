<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\MessageQueueException;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskExecutor;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(ScheduledTaskHandler::class)]
class ScheduledTaskHandlerTest extends TestCase
{
    public function testInvokeDelegatesToExecutorWhenSet(): void
    {
        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([]);

        $handler = new HandlerStub($repository, static::createStub(LoggerInterface::class));
        $handler->setScheduledTaskExecutor(new ScheduledTaskExecutor($repository, static::createStub(LoggerInterface::class), new MockClock()));

        // a task without id is run directly by the executor, without touching the repository
        $task = new HandlerStubTask();

        $handler($task);

        static::assertTrue($handler->wasCalled);
    }

    public function testInvokeThrowsWhenNoExecutorIsSet(): void
    {
        $handler = new HandlerStub(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
        );

        $this->expectExceptionObject(MessageQueueException::scheduledTaskExecutorNotSet(HandlerStub::class));

        $handler(new HandlerStubTask());
    }
}

/**
 * @internal
 */
class HandlerStubTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'test.handler-stub';
    }

    public static function getDefaultInterval(): int
    {
        return 300;
    }
}

/**
 * @internal
 */
class HandlerStub extends ScheduledTaskHandler
{
    public bool $wasCalled = false;

    public function run(): void
    {
        $this->wasCalled = true;
    }
}
