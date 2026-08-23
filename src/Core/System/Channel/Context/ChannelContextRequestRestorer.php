<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Restores a channel context for late fallback/error handling.
 *
 * Use this only when domain resolution has already populated the channel request attributes,
 * but normal route-based context resolution did not run because no route matched.
 *
 * @internal
 */
class ChannelContextRequestRestorer
{
    public function __construct(private readonly ChannelContextServiceInterface $contextService)
    {
    }

    public function restore(Request $request): ?ChannelContext
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);
        if ($context instanceof ChannelContext) {
            return $context;
        }

        $channelId = $request->attributes->getString(PlatformRequest::ATTRIBUTE_CHANNEL_ID);
        if ($channelId === '') {
            return null;
        }

        $domainId = $request->attributes->getString(ChannelRequest::ATTRIBUTE_DOMAIN_ID) ?: null;
        $languageId = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID) ?: null;

        $context = $this->contextService->get(new ChannelContextServiceParameters(
            $channelId,
            Uuid::randomHex(),
            $languageId,
            $domainId,
        ));

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);

        return $context;
    }
}
