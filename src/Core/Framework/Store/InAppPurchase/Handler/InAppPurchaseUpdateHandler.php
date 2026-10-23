<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\InAppPurchase\Handler;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Core\Framework\Store\InAppPurchase\InAppPurchaseUpdateTask;
use Contena\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[AsMessageHandler(handles: InAppPurchaseUpdateTask::class)]
final class InAppPurchaseUpdateHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly InAppPurchaseUpdater $iapUpdater
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $this->iapUpdater->update($context);
    }
}
