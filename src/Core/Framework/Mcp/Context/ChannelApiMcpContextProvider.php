<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Context;

use Contena\Core\Framework\Context;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
class ChannelApiMcpContextProvider implements McpContextProviderInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getChannelContext(): ?ChannelContext
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return null;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        return $context instanceof ChannelContext ? $context : null;
    }

    public function getContext(): Context
    {
        $channelContext = $this->getChannelContext();

        return $channelContext?->getContext() ?? Context::createCLIContext();
    }
}
