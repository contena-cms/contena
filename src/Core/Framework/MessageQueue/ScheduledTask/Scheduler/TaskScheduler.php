<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\ScheduledTask\Scheduler;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MinResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\MessageQueue\MessageQueueException;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @final
 */
readonly class TaskScheduler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        private EntityRepository $scheduledTaskRepository,
        private MessageBusInterface $bus,
        private ParameterBagInterface $parameterBag,
        private LoggerInterface $logger,
        private int $requeueTimeout,
        private ClockInterface $clock,
    ) {
    }

    public function queueScheduledTasks(): void
    {
        $criteria = $this->buildCriteriaForAllScheduledTask();
        $context = Context::createDefaultContext();
        $tasks = $this->scheduledTaskRepository->search($criteria, $context)->getEntities();

        if (\count($tasks) === 0) {
            return;
        }

        foreach ($tasks as $task) {
            $this->queueTask($task, $context);
        }
    }

    public function getMinRunInterval(): ?int
    {
        $criteria = $this->buildCriteriaForMinRunInterval();
        $aggregation = $this->scheduledTaskRepository
            ->aggregate($criteria, Context::createDefaultContext())
            ->get('runInterval');

        /** @var MinResult $aggregation */
        if (!$aggregation instanceof MinResult) {
            return null;
        }
        if ($aggregation->getMin() === null) {
            return null;
        }

        return (int) $aggregation->getMin();
    }

    private function buildCriteriaForAllScheduledTask(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new OrFilter(
                [
                    // all regular tasks that have reached their next execution time
                    new AndFilter(
                        [
                            new RangeFilter(
                                'nextExecutionTime',
                                [
                                    RangeFilter::LT => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                                ]
                            ),
                            new EqualsAnyFilter('status', [
                                ScheduledTaskDefinition::STATUS_SCHEDULED,
                                ScheduledTaskDefinition::STATUS_SKIPPED,
                            ]),
                        ]
                    ),
                    // requeue tasks that are stuck in "running" or "queued" state for more than 12 hours
                    // we assume that either the message was lost or the worker crashed
                    new AndFilter(
                        [
                            new RangeFilter(
                                'updatedAt',
                                [
                                    RangeFilter::LT => $this->clock->now()
                                        ->modify(\sprintf('-%d hours', $this->requeueTimeout))
                                        ->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                                ]
                            ),
                            new EqualsAnyFilter('status', [
                                ScheduledTaskDefinition::STATUS_QUEUED,
                                ScheduledTaskDefinition::STATUS_RUNNING,
                            ]),
                        ]
                    ),
                ]
            )
        );

        return $criteria;
    }

    private function queueTask(ScheduledTaskEntity $taskEntity, Context $context): void
    {
        $taskClass = $taskEntity->getScheduledTaskClass();

        if (!class_exists($taskClass)) {
            $this->logger->warning(\sprintf(
                'Scheduled task class "%s" does not exist, this might be due to version mismatch during deployments when a new scheduled task is already registered, but the worker still running on an older version where that task does not exist yet.',
                $taskClass
            ));

            return;
        }

        if (!\is_a($taskClass, ScheduledTask::class, true)) {
            throw MessageQueueException::scheduledTaskDoesNotImplementInterface($taskClass);
        }

        if (!$taskClass::shouldRun($this->parameterBag)) {
            $this->scheduledTaskRepository->update([
                [
                    'id' => $taskEntity->getId(),
                    'nextExecutionTime' => $this->calculateNextExecutionTime($taskEntity),
                    'status' => ScheduledTaskDefinition::STATUS_SKIPPED,
                ],
            ], $context);

            return;
        }

        // Tasks **must not** be queued before their state in the database has been updated. Otherwise,
        // a worker could have already fetched the task and set its state to running before it gets set to
        // queued, thus breaking the task.
        $this->scheduledTaskRepository->update([
            [
                'id' => $taskEntity->getId(),
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
            ],
        ], $context);

        $task = new $taskClass();
        $task->setTaskId($taskEntity->getId());

        $this->bus->dispatch($task);
    }

    private function buildCriteriaForMinRunInterval(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsFilter('status', ScheduledTaskDefinition::STATUS_INACTIVE),
                new EqualsFilter('status', ScheduledTaskDefinition::STATUS_SKIPPED),
            ])
        )
        ->addAggregation(new MinAggregation('runInterval', 'runInterval'));

        return $criteria;
    }

    private function calculateNextExecutionTime(ScheduledTaskEntity $taskEntity): \DateTimeImmutable
    {
        $now = $this->clock->now();

        $nextExecutionTimeString = $taskEntity->getNextExecutionTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $nextExecutionTime = new \DateTimeImmutable($nextExecutionTimeString);
        $newNextExecutionTime = $nextExecutionTime->modify(\sprintf('+%d seconds', $taskEntity->getRunInterval()));

        return $newNextExecutionTime < $now ? $now : $newNextExecutionTime;
    }
}
