<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Notification;

use Contena\Core\Framework\App\Feature\AppFeatureStorage;
use Contena\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Contena\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Contena\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Contena\Core\Framework\App\Mcp\Mcp;

/**
 * @internal
 */
class AppMcpCapabilityDetector
{
    public function __construct(
        private readonly AppFeatureStorage $storage,
    ) {
    }

    public function persistedForApp(string $appId): McpListChangedNotificationSet
    {
        return new McpListChangedNotificationSet(
            tools: $this->storage->forApp($appId, McpToolConfig::class) !== [],
            resources: $this->storage->forApp($appId, McpResourceConfig::class) !== [],
            prompts: $this->storage->forApp($appId, McpPromptConfig::class) !== [],
        );
    }

    public function fromMcp(?Mcp $mcp): McpListChangedNotificationSet
    {
        if ($mcp === null) {
            return McpListChangedNotificationSet::none();
        }

        return new McpListChangedNotificationSet(
            tools: $mcp->getTools() !== null && $mcp->getTools()->getTools() !== [],
            resources: $mcp->getResources() !== null && $mcp->getResources()->getResources() !== [],
            prompts: $mcp->getPrompts() !== null && $mcp->getPrompts()->getPrompts() !== [],
        );
    }
}
