<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: InvalidateCacheTask::class)]
final class InvalidateCacheTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly CacheInvalidator $cacheInvalidator
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        try {
            $this->cacheInvalidator->invalidateExpired();
        } catch (\Throwable) {
        }
    }
}
