<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Result;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Names the principal a stored tool result belongs to, derived from the authenticated request:
 * the integration and admin user on the Admin API, the sales channel and context token on the Store API.
 * A Store API context token changes on login and logout, so a result stored before that is no
 * longer readable afterwards. A Store API call without `sw-context-token` has no principal, so its
 * result is returned inline.
 */
final class McpToolResultPrincipal
{
    public static function fromRequest(Request $request): ?string
    {
        $channelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);
        if ($channelContext instanceof ChannelContext) {
            // Without `sw-context-token` the context resolver mints a random token for this request only, so
            // the caller could never read the result back. The resolver writes that token into the headers,
            // but not into the server parameters, which still show what the client sent.
            if (!(new HeaderBag($request->server->getHeaders()))->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
                return null;
            }

            return 'store:' . $channelContext->getChannelId() . ':' . $channelContext->getToken();
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            return null;
        }

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            return null;
        }

        $integrationId = $source->getIntegrationId();
        $adminUserId = $source->getUserId();
        if ($integrationId === null && $adminUserId === null) {
            return null;
        }

        return 'admin:' . ($integrationId ?? '') . ':' . ($adminUserId ?? '');
    }
}
