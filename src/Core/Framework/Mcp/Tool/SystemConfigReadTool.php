<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\Attribute\McpToolRequires;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @experimental stableVersion:v6.8.0
 */
#[McpTool(
    name: 'contena-system-config-read',
    title: 'System Config Read',
    description: 'Read Contena application configuration values in the core.* namespace. Pass a domain prefix (e.g. "core.listing") to get all keys, or a full dotted key to read a single value. For theme appearance settings (colors, logos, fonts), use contena-theme-config instead. Optionally scope to a sales channel.'
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
            $value = $this->systemConfigService->get($key, $channelId);

            return $this->success(['key' => $key, 'value' => $value]);
        }

        $domain = $this->systemConfigService->getDomain($key, $channelId);

        return $this->success(['domain' => $key, 'values' => $domain]);
    }
}
