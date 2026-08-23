<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Context;

use Contena\Core\Framework\Context;

/**
 * Common interface for MCP context providers.
 *
 * Implementations resolve a Contena Context from the current request so that
 * MCP tools can perform DAL operations without knowing how the request context is resolved.
 *
 * The Administration API context is resolved via OAuth bearer token or integration credentials.
 */
interface McpContextProviderInterface
{
    public function getContext(): Context;
}
