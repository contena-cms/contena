<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\Context\ChannelApiMcpContextProvider;
use Contena\Core\Framework\Mcp\Tool\McpToolResponse;

/**
 * @internal
 */
#[McpTool(
    name: 'contena-channel-api-context',
    title: 'Channel API Context',
    description: 'Read the current Channel API context for this MCP session, including channel, language, context token, and whether a member is authenticated.'
)]
#[McpToolGroup('channel-api')]
class ChannelApiContextTool extends McpToolResponse
{
    public function __construct(
        private readonly ChannelApiMcpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(): string
    {
        $context = $this->contextProvider->getChannelContext();

        if ($context === null) {
            return $this->error('No Channel API context is available for this MCP request.');
        }

        $member = $context->getMember();

        return $this->success([
            'channelId' => $context->getChannelId(),
            'token' => $context->getToken(),
            'languageId' => $context->getLanguageId(),
            'memberAuthenticated' => $member !== null,
            'memberId' => $member?->getId(),
        ]);
    }
}
