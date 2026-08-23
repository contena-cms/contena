<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Session;

use Contena\Core\Framework\Mcp\McpException;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

class McpSessionIdValidator
{
    /**
     * The MCP SDK transport parses the session header with Uuid::fromString(),
     * which throws on malformed input. Reject garbage early with a clean 400
     * instead of surfacing a 500 from the transport.
     */
    public function validate(Request $request): void
    {
        $sessionId = $request->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID);

        if ($sessionId !== null && !Uuid::isValid($sessionId)) {
            throw McpException::invalidSessionId();
        }
    }
}
