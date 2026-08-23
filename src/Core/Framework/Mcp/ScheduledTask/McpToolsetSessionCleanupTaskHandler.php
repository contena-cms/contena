<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\ScheduledTask;

use Mcp\Server\Session\SessionStoreInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Mcp\McpToolsetSessionStorage;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * Removes abandoned mcp_toolset_session rows. Rows are normally deleted when the client sends
 * DELETE /api/_mcp, but a client that disconnects without a DELETE would otherwise leave its rows
 * behind forever. Cleanup is tied strictly to the MCP session store's own liveness: a row is
 * dropped only once its session no longer exists in the store. The store expires a session once it
 * has been idle past its TTL, so an active session (however old) is never purged, while an
 * abandoned one is reclaimed after it expires. created_at is deliberately not used as a delete
 * criterion, because an active session can outlive any fixed age.
 */
#[AsMessageHandler(handles: McpToolsetSessionCleanupTask::class)]
final class McpToolsetSessionCleanupTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly McpToolsetSessionStorage $sessionStorage,
        private readonly SessionStoreInterface $sessionStore,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        foreach ($this->sessionStorage->sessionIds() as $sessionId) {
            try {
                $uuid = Uuid::fromString($sessionId);
            } catch (\InvalidArgumentException) {
                $this->sessionStorage->deleteForSession($sessionId);

                continue;
            }

            if (!$this->sessionStore->exists($uuid)) {
                $this->sessionStorage->deleteForSession($sessionId);
            }
        }
    }
}
