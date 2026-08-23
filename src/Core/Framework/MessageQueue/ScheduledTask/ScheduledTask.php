<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\ScheduledTask;

use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

abstract class ScheduledTask implements ScheduledTaskMessageInterface, AsyncMessageInterface
{
    protected const int MINUTELY = 60;
    protected const int HOURLY = 3600;
    protected const int DAILY = 86400;
    protected const int WEEKLY = 604800;

    protected ?string $taskId = null;

    /**
     * @internal
     */
    final public function __construct()
    {
        // needs to be empty
    }

    public function getTaskId(): ?string
    {
        return $this->taskId;
    }

    public function setTaskId(string $taskId): void
    {
        $this->taskId = $taskId;
    }

    abstract public static function getTaskName(): string;

    /**
     * @return int the default interval this task should run in seconds
     */
    abstract public static function getDefaultInterval(): int;

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return true;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return false;
    }
}
