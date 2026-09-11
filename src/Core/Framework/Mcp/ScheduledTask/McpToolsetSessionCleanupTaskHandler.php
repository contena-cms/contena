<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\ScheduledTask;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Mcp\McpToolsetSessionStorage;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Mcp\Server\Session\SessionStoreInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * Removes abandoned mcp_toolset_session rows. Rows are normally deleted when the client sends
 * DELETE /api/_mcp, but a client that disconnects without a DELETE would otherwise leave its rows
 * behind forever. Cleanup is tied strictly to the MCP session stores' own liveness: a row is
 * dropped only once its session no longer exists in any of them. A store expires a session once it
 * has been idle past its TTL, so an active session (however old) is never purged, while an
 * abandoned one is reclaimed after it expires. created_at is deliberately not used as a delete
 * criterion, because an active session can outlive any fixed age.
 *
 * Rows are keyed on the raw Mcp-Session-Id and are not namespaced per endpoint, while each MCP
 * server owns its own session store. Every store therefore has to be consulted — checking only the
 * Admin API store would treat every live Channel API session as abandoned and drop its toolsets.
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
        private readonly SessionStoreInterface $channelApiSessionStore,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $sessionStores = [$this->sessionStore, $this->channelApiSessionStore];

        foreach ($this->sessionStorage->sessionIds() as $sessionId) {
            try {
                $uuid = Uuid::fromString($sessionId);
            } catch (\InvalidArgumentException) {
                $this->sessionStorage->deleteForSession($sessionId);

                continue;
            }

            foreach ($sessionStores as $sessionStore) {
                if ($sessionStore->exists($uuid)) {
                    continue 2;
                }
            }

            $this->sessionStorage->deleteForSession($sessionId);
        }
    }
}
