<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ScheduledTask;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\App\Lifecycle\Update\AbstractAppUpdater;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: UpdateAppsTask::class)]
final class UpdateAppsHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly AbstractAppUpdater $appUpdater
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->appUpdater->updateApps(Context::createCLIContext());
    }
}
