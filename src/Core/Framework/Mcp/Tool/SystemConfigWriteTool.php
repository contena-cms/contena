<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\Attribute\McpToolRequires;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\System\SystemConfig\SystemConfigService;

#[McpTool(
    name: 'contena-system-config-write',
    title: 'System Config Write',
    description: 'Modify or overwrite a Contena system configuration value. Provide a dotted key and JSON or scalar value. dryRun=true previews the change. Optionally scope to a channel.'
)]
#[McpToolDependsOn('contena-system-config-read')]
#[McpToolGroup('system-config')]
#[McpToolRequires('system_config:update')]
class SystemConfigWriteTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(string $key, string $value, ?string $channelId = null, bool $dryRun = true): string
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'system_config:update')) {
            return $error;
        }

        $decodedValue = json_decode($value, true);
        $actualValue = json_last_error() === \JSON_ERROR_NONE ? $decodedValue : $value;

        if ($actualValue === null) {
            return $this->error('Setting null is not supported via MCP as it would delete the config entry. Use the Admin API to delete configuration values.');
        }

        $oldValue = $this->systemConfigService->get($key, $channelId, $context);

        if ($dryRun) {
            return $this->success([
                'key' => $key,
                'oldValue' => $oldValue,
                'newValue' => $actualValue,
            ], ['dryRun' => true, 'channelId' => $channelId]);
        }

        $this->systemConfigService->set($key, $actualValue, $channelId, context: $context);

        return $this->success([
            'key' => $key,
            'oldValue' => $oldValue,
            'newValue' => $actualValue,
        ], ['dryRun' => false, 'channelId' => $channelId]);
    }
}
