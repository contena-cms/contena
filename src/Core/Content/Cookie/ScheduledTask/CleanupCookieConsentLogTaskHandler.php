<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\ScheduledTask;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Contena\Tests\Integration\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTaskHandlerTest;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Deletes cookie consent log entries older than the configured retention period
 * and removes banner configuration snapshots that are no longer referenced.
 *
 * Deletion happens in batches so large tables do not hold locks for too long.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see CleanupCookieConsentLogTaskHandlerTest
 */
#[AsMessageHandler(handles: CleanupCookieConsentLogTask::class)]
final class CleanupCookieConsentLogTaskHandler extends ScheduledTaskHandler
{
    public const CONFIG_KEY_RETENTION_DAYS = 'core.cookieConsent.logRetentionDays';

    public const DEFAULT_RETENTION_DAYS = 120;

    private const DELETE_BATCH_SIZE = 10000;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly TenantScopeContextProvider $tenantScopeContextProvider,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        foreach ($this->tenantScopeContextProvider->getContexts() as $context) {
            $this->cleanup($context);
        }
    }

    private function cleanup(Context $context): void
    {
        $retentionDays = $this->systemConfigService->getInt(self::CONFIG_KEY_RETENTION_DAYS, context: $context) ?: self::DEFAULT_RETENTION_DAYS;

        // A negative value disables the cleanup, the operator keeps the log forever
        if ($retentionDays < 0) {
            return;
        }

        $deleteBefore = $this->clock->now()
            ->sub(new \DateInterval(\sprintf('P%dD', $retentionDays)))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $tenantId = $context->getTenantId();
        $tenantCondition = '`tenant_id` IS NULL';
        $parameters = ['before' => $deleteBefore];
        if ($tenantId !== null) {
            $tenantCondition = '`tenant_id` = :tenantId';
            $parameters['tenantId'] = Uuid::fromHexToBytes($tenantId);
        }

        do {
            $deleted = $this->connection->executeStatement(
                \sprintf(
                    'DELETE FROM `cookie_consent_log` WHERE %s AND `created_at` < :before LIMIT %d',
                    $tenantCondition,
                    self::DELETE_BATCH_SIZE,
                ),
                $parameters,
            );
        } while ($deleted === self::DELETE_BATCH_SIZE);

        // Snapshots are kept as long as any log entry references them. The created_at guard
        // avoids deleting a snapshot that a concurrent, not yet committed consent references.
        $this->connection->executeStatement(
            \sprintf(
                'DELETE `version` FROM `cookie_consent_config_version` AS `version`
                LEFT JOIN `cookie_consent_log` AS `log`
                    ON `log`.`tenant_id` <=> `version`.`tenant_id`
                    AND `log`.`config_hash` = `version`.`config_hash`
                WHERE `version`.%s AND `log`.`id` IS NULL AND `version`.`created_at` < :before',
                $tenantCondition,
            ),
            $parameters,
        );
    }
}
