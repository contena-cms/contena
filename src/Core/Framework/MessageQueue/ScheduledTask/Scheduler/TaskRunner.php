<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\ScheduledTask\Scheduler;

use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\MessageQueue\MessageQueueException;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
class TaskRunner
{
    /**
     * @param iterable<object> $taskHandler
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        private readonly iterable $taskHandler,
        private readonly EntityRepository $scheduledTaskRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function runSingleTask(string $taskName, Context $context): void
    {
        $scheduledTask = $this->fetchTask($taskName, $context);

        // Set status to allow running it
        $this->scheduledTaskRepository->update([
            [
                'id' => $scheduledTask->getId(),
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
                'nextExecutionTime' => $this->clock->now(),
            ],
        ], $context);

        // Create task
        /** @var class-string<ScheduledTask> $className */
        $className = $scheduledTask->getScheduledTaskClass();
        $task = new $className();
        $task->setTaskId($scheduledTask->getId());

        foreach ($this->taskHandler as $handler) {
            if (!$handler instanceof ScheduledTaskHandler) {
                continue;
            }

            $reflection = new \ReflectionClass($handler);
            $asMessage = $reflection->getAttributes(AsMessageHandler::class);

            if ($asMessage === []) {
                continue;
            }

            foreach ($asMessage as $attribute) {
                /** @var AsMessageHandler $messageAttribute */
                $messageAttribute = $attribute->newInstance();

                if ($messageAttribute->handles === $className) {
                    // calls the __invoke() method of the abstract ScheduledTaskHandler
                    $handler($task);

                    return;
                }
            }
        }
    }

    private function fetchTask(string $taskName, Context $context): ScheduledTaskEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $taskName));

        /** @var ScheduledTaskEntity|null $task */
        $task = $this->scheduledTaskRepository->search($criteria, $context)->getEntities()->first();

        if ($task === null) {
            throw MessageQueueException::cannotFindTaskByName($taskName);
        }

        return $task;
    }
}
