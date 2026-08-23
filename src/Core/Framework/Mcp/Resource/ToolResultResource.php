<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Server\RequestContext;
use Contena\Core\Framework\Mcp\McpException;
use Contena\Core\Framework\Mcp\ToolResultCacheStorage;

/**
 * Serves a large tool result that was stored during the current MCP session.
 * Access is restricted to the session that created the result.
 */
#[McpResourceTemplate(
    uriTemplate: 'contena://tool-result/{id}',
    name: 'tool-result',
    description: 'Retrieves a large tool result stored by a previous tool call in this session. Fetch when a tool response contains a resourceUri pointing here.',
    mimeType: 'application/json',
)]
class ToolResultResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ToolResultCacheStorage $storage,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(string $id, RequestContext $context): array
    {
        $sessionId = $context->getSession()->getId()->toString();
        $result = $this->storage->read($id, $sessionId);

        if ($result === null) {
            throw McpException::toolResultNotFound($id);
        }

        return [
            'uri' => 'contena://tool-result/' . $id,
            'mimeType' => $result['mimeType'],
            'text' => $result['content'],
        ];
    }
}
