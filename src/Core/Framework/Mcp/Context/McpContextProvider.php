<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Context;

use Contena\Core\Framework\Context;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Bridges the authenticated HTTP request context into MCP tool invocations.
 * The MCP bundle's HTTP transport processes requests through Contena's API middleware,
 * so the Context is already resolved and attached to the request by ApiRequestContextResolver.
 */
class McpContextProvider implements McpContextProviderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getContext(): Context
    {
        $request = $this->requestStack->getMainRequest();

        if ($request !== null) {
            $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

            if ($context instanceof Context) {
                return $context;
            }
        }

        return Context::createCLIContext();
    }
}
