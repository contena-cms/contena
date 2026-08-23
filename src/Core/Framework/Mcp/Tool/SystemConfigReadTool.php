<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\Attribute\McpToolRequires;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\System\SystemConfig\SystemConfigService;

#[McpTool(
    name: 'contena-system-config-read',
    title: 'System Config Read',
    description: 'Read Contena application configuration values. Pass a domain prefix to get all keys, or a full dotted key to read one value. Optionally scope to a channel.'
)]
#[McpToolGroup('system-config')]
#[McpToolRequires('system_config:read')]
class SystemConfigReadTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(string $key, ?string $channelId = null): string
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'system_config:read')) {
            return $error;
        }

        if (str_contains($key, '.') && substr_count($key, '.') >= 2) {
            $value = $this->systemConfigService->get($key, $channelId, $context);

            return $this->success(['key' => $key, 'value' => $value]);
        }

        $domain = $this->systemConfigService->getDomain($key, $channelId, context: $context);

        return $this->success(['domain' => $key, 'values' => $domain]);
    }
}
