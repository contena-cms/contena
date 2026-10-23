<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Notification;

use Contena\Core\Framework\App\Feature\AppFeatureStorage;
use Contena\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Contena\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Contena\Core\Framework\App\Mcp\Feature\McpToolConfig;

/**
 * @experimental stableVersion:v6.8.0
 *
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
}
