<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\MessageQueue\ScheduledTask;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\MessageQueue\Command\RegisterScheduledTasksCommand;
use Contena\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class RegisterScheduledTaskTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testNoValidationErrors(): void
    {
        $taskRegistry = $this->createMock(TaskRegistry::class);
        $taskRegistry->expects($this->once())
            ->method('registerTasks');

        $commandTester = new CommandTester(new RegisterScheduledTasksCommand($taskRegistry));
        $commandTester->execute([]);
    }
}
