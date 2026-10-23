<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\Context\StoreApiMcpContextProvider;
use Contena\Core\Framework\Mcp\Tool\McpToolResponse;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[McpTool(
    name: 'contena-store-api-context',
    title: 'Store API Context',
    description: 'Read the current Store API sales-channel context for this MCP session, including sales channel, language, currency, context token, and whether a user is authenticated.'
)]
#[McpToolGroup('store-api')]
class StoreApiContextTool extends McpToolResponse
{
    public function __construct(
        private readonly StoreApiMcpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(): string
    {
        $context = $this->contextProvider->getChannelContext();

        if ($context === null) {
            return $this->error('No Store API sales-channel context is available for this MCP request.');
        }

        $user = $context->getUser();

        return $this->success([
            'channelId' => $context->getChannelId(),
            'token' => $context->getToken(),
            'languageId' => $context->getLanguageId(),
            'currencyId' => $context->getCurrencyId(),
            'userAuthenticated' => $user !== null,
            'userId' => $user?->getId(),
        ]);
    }
}
