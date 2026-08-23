<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Notification;

use Mcp\Schema\Notification\PromptListChangedNotification;
use Mcp\Schema\Notification\ResourceListChangedNotification;
use Mcp\Schema\Notification\ToolListChangedNotification;
use Mcp\Server\Session\SessionStoreInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Contena\Core\Framework\Util\Exception\JsonDecodingException;
use Contena\Core\Framework\Util\Json;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
class McpListChangedNotifier
{
    /**
     * Request attribute a tool sets to ask its MCP controller to emit a tools/listChanged for the
     * current session. The controller flushes it only after the SDK has persisted its in-memory
     * session (see notifySession()), so the queued notification is not overwritten.
     */
    final public const PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE = 'contena.mcp.pending_tools_list_changed';

    private const SESSION_OUTGOING_QUEUE = '_mcp';
    private const SESSION_OUTGOING_QUEUE_KEY = 'outgoing_queue';

    public function __construct(
        private readonly SessionStoreInterface $sessionStore,
        private readonly McpSessionRegistry $sessionRegistry,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Broadcasts a list_changed notification to every active MCP session. Use only for
     * installation-wide capability changes (e.g. plugin install/uninstall); this is O(active sessions)
     * per call. For a change that only affects one session, use notifySession() instead.
     */
    public function notify(McpListChangedNotificationSet $notifications): void
    {
        if (!$notifications->hasChanges()) {
            return;
        }

        $messages = $this->buildMessages($notifications);

        foreach ($this->sessionRegistry->all() as $sessionId) {
            $this->queueForSession($sessionId, $messages, $this->sessionStore);
        }
    }

    /**
     * Queues a list_changed notification for a single MCP session. Use this for session-local
     * changes (e.g. enabling a toolset for the current session) so the work stays O(1) instead of
     * touching every active session.
     *
     * This writes directly to the session store, so for the session of the current request it must
     * be called AFTER the MCP SDK has persisted its in-memory session (i.e. after the server run
     * completes); otherwise the SDK's own save overwrites the queued notification. Callers inside a
     * tool must defer via {@see self::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE} instead of calling this
     * during the tool invocation.
     */
    public function notifySession(string $sessionId, McpListChangedNotificationSet $notifications): void
    {
        if (!$notifications->hasChanges()) {
            return;
        }

        $this->queueForSession($sessionId, $this->buildMessages($notifications), $this->sessionStore);
    }

    /**
     * @param list<string> $messages
     */
    private function queueForSession(string $sessionId, array $messages, SessionStoreInterface $sessionStore): void
    {
        try {
            $uuid = Uuid::fromString($sessionId);
        } catch (\InvalidArgumentException) {
            $this->sessionRegistry->remove($sessionId);

            return;
        }

        if (!$sessionStore->exists($uuid)) {
            $this->sessionRegistry->remove($sessionId);

            return;
        }

        try {
            $rawSession = $sessionStore->read($uuid);
            $sessionData = $rawSession !== false ? Json::decodeToArray($rawSession) : [];
        } catch (JsonDecodingException $exception) {
            $this->logger->warning('Skipping MCP list_changed notification for unreadable session data.', [
                'sessionId' => $sessionId,
                'exception' => $exception,
            ]);

            return;
        }

        $mcpData = $sessionData[self::SESSION_OUTGOING_QUEUE] ?? [];
        if (!\is_array($mcpData)) {
            $mcpData = [];
        }

        $queue = $mcpData[self::SESSION_OUTGOING_QUEUE_KEY] ?? [];
        if (!\is_array($queue)) {
            $queue = [];
        }

        foreach ($messages as $message) {
            $queue[] = [
                'message' => $message,
                'context' => ['type' => 'notification'],
            ];
        }

        $mcpData[self::SESSION_OUTGOING_QUEUE_KEY] = $queue;
        $sessionData[self::SESSION_OUTGOING_QUEUE] = $mcpData;
        $sessionStore->write($uuid, Json::encode($sessionData));
    }

    /**
     * @return list<string>
     */
    private function buildMessages(McpListChangedNotificationSet $notifications): array
    {
        $messages = [];

        if ($notifications->tools) {
            $messages[] = $this->createNotification(ToolListChangedNotification::getMethod());
        }

        if ($notifications->resources) {
            $messages[] = $this->createNotification(ResourceListChangedNotification::getMethod());
        }

        if ($notifications->prompts) {
            $messages[] = $this->createNotification(PromptListChangedNotification::getMethod());
        }

        return $messages;
    }

    private function createNotification(string $method): string
    {
        return Json::encode([
            'jsonrpc' => '2.0',
            'method' => $method,
        ]);
    }
}
