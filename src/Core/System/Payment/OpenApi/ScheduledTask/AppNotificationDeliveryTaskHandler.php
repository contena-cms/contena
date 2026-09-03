<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\ScheduledTask;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Core\System\Payment\OpenApi\AppNotificationService;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Contena\Tests\Integration\Core\System\Payment\OpenApi\AppNotificationServiceTest;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see AppNotificationServiceTest
 */
#[AsMessageHandler(handles: AppNotificationDeliveryTask::class)]
final class AppNotificationDeliveryTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly AppNotificationService $notificationService,
        private readonly TenantScopeContextProvider $tenantScopeContextProvider,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        foreach ($this->tenantScopeContextProvider->getContexts() as $context) {
            $this->notificationService->deliverPending($context);
        }
    }
}
